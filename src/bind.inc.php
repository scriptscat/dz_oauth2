<?php

if (!defined('IN_DISCUZ')) {
    exit('Access Denied');
}

require_once DISCUZ_ROOT . '/source/plugin/codfrm_oauth2/table/table_oauth_scriptcat.php';
require_once DISCUZ_ROOT . '/source/plugin/codfrm_oauth2/lib/scriptcat.php';
require_once DISCUZ_ROOT . '/source/plugin/codfrm_oauth2/lib/utils.php';
require_once DISCUZ_ROOT . '/source/function/function_member.php';
require_once DISCUZ_ROOT . '/source/class/class_member.php';

global $_G;
$setting = $_G['cache']['plugin']['codfrm_oauth2'];

switch ($_GET['op'] ?? '') {
    case 'redirect':
        handleScriptcatRedirect();
        break;
    default:
        showError('错误的操作');
}

/**
 * 生成 HMAC 签名的 state（短格式，URL 安全）
 * 格式: nonce.timestamp.hmac（用 . 分隔，避免 URL 编码膨胀）
 */
function generateSignedState()
{
    global $_G;
    $key = $_G['config']['security']['authkey'];
    $nonce = bin2hex(random_bytes(8));
    $ts = time();
    $hmac = substr(hash_hmac('sha256', "$nonce.$ts", $key), 0, 16);
    return "$nonce.$ts.$hmac";
}

/**
 * 验证签名 state
 */
function verifySignedState($state, $maxAge = 600)
{
    global $_G;
    $key = $_G['config']['security']['authkey'];

    $parts = explode('.', $state, 3);
    if (count($parts) !== 3) {
        return false;
    }

    list($nonce, $ts, $hmac) = $parts;

    if (abs(time() - intval($ts)) > $maxAge) {
        return false;
    }

    $expectedHmac = substr(hash_hmac('sha256', "$nonce.$ts", $key), 0, 16);
    return hash_equals($expectedHmac, $hmac);
}

/**
 * ScriptCat OAuth 登录流程
 */
function handleScriptcatRedirect()
{
    global $_G;
    $setting = $_G['cache']['plugin']['codfrm_oauth2'];

    if (!$setting['scriptcat_oauth_client_id'] || !$setting['scriptcat_oauth_host']) {
        showError('当前站点暂未设置 ScriptCat 登录', 3);
    }

    $code = $_GET['code'] ?? '';
    if (!$code) {
        // 第一次访问: 重定向到 ScriptCat 授权页
        $sc = newScriptCatClient();
        $redirectUri = getScriptcatRedirectUri('redirect');

        // referer 用明文 cookie 存储（同域设置同域读，不依赖 authcode）
        $rawReferer = $_GET['referer'] ?? dreferer();
        while (strpos($rawReferer, '%') !== false && urldecode($rawReferer) !== $rawReferer) {
            $rawReferer = urldecode($rawReferer);
        }
        $referer = sanitizeReferer($rawReferer);
        dsetcookie('sc_login_referer', $referer, 600);

        $state = generateSignedState();

        $authorizeUrl = $sc->authorizeUrl($redirectUri, 'openid', $state);
        dheader('Location: ' . $authorizeUrl);
        return;
    }

    // 回调: 验证签名 state
    $state = $_GET['state'] ?? '';
    if (!verifySignedState($state)) {
        showError('请求验证失败，请重新登录', 3);
    }

    // 用 code 换取用户信息
    $userinfo = fetchScriptcatUserInfo($code, 'redirect');
    $scriptcatUid = $userinfo['uid'];
    $username = $userinfo['username'];
    $email = $userinfo['email'] ?? '';

    // 1. 检查绑定表
    $table = new table_oauth_scriptcat();
    $binding = $table->fetchByScriptcat($scriptcatUid);

    if ($binding) {
        scriptcatAutoLogin($binding['uid']);
        return;
    }

    // 2. 没有绑定记录，自动创建新用户并绑定
    createAndBindScriptcatUser($table, $scriptcatUid, $username, $email);
}

/**
 * 创建新用户并写入绑定记录（事务保护）
 */
function createAndBindScriptcatUser($table, $scriptcatUid, $username, $email)
{
    $newUid = createDiscuzUserFromScriptcat($scriptcatUid, $username, $email);
    if (!$newUid) {
        showError('创建账号失败，请联系管理员', 5);
    }

    DB::query("BEGIN");
    try {
        $existing = $table->fetchByScriptcat($scriptcatUid);
        if ($existing) {
            DB::query("ROLLBACK");
            scriptcatAutoLogin($existing['uid']);
            return;
        }

        $table->insert([
            'uid' => $newUid,
            'openid' => $scriptcatUid,
            'name' => $username,
            'createtime' => time(),
        ]);
        DB::query("COMMIT");
    } catch (Exception $e) {
        DB::query("ROLLBACK");
        error_log('ScriptCat bind insert error: ' . $e->getMessage());
        $existing = $table->fetchByScriptcat($scriptcatUid);
        if ($existing) {
            scriptcatAutoLogin($existing['uid']);
            return;
        }
        showError('绑定账号失败，请联系管理员', 5);
    }

    scriptcatAutoLogin($newUid);
}

/**
 * 根据 ScriptCat 用户信息自动创建 Discuz 用户
 */
function createDiscuzUserFromScriptcat($scriptcatUid, $username, $email)
{
    global $_G;

    $existing = C::t('common_member')->fetch_by_username($username);
    if ($existing) {
        showError('用户名已被占用，请联系管理员', 5);
    }
    $finalUsername = $username;

    if (!$email) {
        showError('ScriptCat 账号未设置邮箱，请先设置邮箱后再登录', 5);
    }
    $password = generateRandomString(16);

    loaducenter();
    $ucUid = uc_user_register($finalUsername, $password, $email, '', '', $_G['clientip']);
    if ($ucUid <= 0) {
        $ucErrors = array(
            -1 => '用户名不合法',
            -2 => '包含不允许注册的词语',
            -3 => '邮箱已被注册，请使用其他邮箱',
            -4 => '邮箱格式不正确',
            -5 => '邮箱域名不允许注册',
            -6 => '该用户名已被注册',
        );
        $errMsg = $ucErrors[$ucUid] ?? 'UCenter注册失败(错误码:' . $ucUid . ')';
        showError($errMsg, 5);
    }

    $groupid = $_G['setting']['newusergroupid'] ?? 10;
    C::t('common_member')->insert_user($ucUid, $finalUsername, $password, $email, $_G['clientip'], $groupid, array('emailstatus' => 1));

    return $ucUid;
}

/**
 * 用 code 换取 ScriptCat 用户信息
 */
function fetchScriptcatUserInfo($code, $op = 'redirect')
{
    global $_G;

    if (!$code) {
        showError('错误请求', 3);
    }

    $sc = newScriptCatClient();
    $redirectUri = getScriptcatRedirectUri($op);
    $resp = $sc->accessToken($code, $redirectUri);
    if (!$resp) {
        showError('系统网络错误,请反馈给网站管理员', 5);
    }

    if (!isset($resp['access_token'])) {
        $errMsg = $resp['error_description'] ?? $resp['message'] ?? '未知错误';
        error_log('ScriptCat OAuth token error: ' . $errMsg);
        showError('系统错误,请反馈给网站管理员', 5);
    }

    $userinfo = $sc->userinfo($resp['access_token']);

    if (!$userinfo || !isset($userinfo['uid'])) {
        showError('获取用户信息失败,请反馈给网站管理员', 5);
    }

    return $userinfo;
}

function newScriptCatClient()
{
    global $_G;
    $setting = $_G['cache']['plugin']['codfrm_oauth2'];
    return new ScriptCat(
        $setting['scriptcat_oauth_client_id'],
        $setting['scriptcat_oauth_client_secret'],
        $setting['scriptcat_oauth_host'] ?? ''
    );
}

function getScriptcatRedirectUri($op = 'redirect')
{
    global $_G;
    return $_G['siteurl'] . 'plugin.php?id=codfrm_oauth2:bind&op=' . $op;
}

/**
 * 自动登录指定 uid 的 Discuz 用户
 */
function scriptcatAutoLogin($uid)
{
    global $_G;

    require_once libfile('function/member');
    require_once libfile('function/core');

    if ($_G['style']) {
        $_G['style']['defaultextstyle'] = '';
    }
    if ($_G['setting']) {
        $_G['setting']['shortcut'] = '';
        $_G['setting']['showpatchnotice'] = 1;
    }
    if ($_G['cookie']) {
        $_G['cookie']['ulastactivity'] = getglobal('cookie/ulastactivity');
    }

    $member = getuserbyuid($uid, 1);
    if (!$member) {
        showError('用户不存在', 5);
    }

    $cookietime = 1296000;
    setloginstatus($member, $cookietime);

    $referer = sanitizeReferer(getcookie('sc_login_referer') ?: dreferer());
    dsetcookie('sc_login_referer', '', -1);

    openMessage('登录成功,3秒后跳转', $referer ?: $_G['siteurl']);
}
