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
        $referer = sanitizeReferer($_GET['referer'] ?? dreferer());

        ensureSession();
        $state = bin2hex(random_bytes(16));
        $_SESSION['scriptcat_oauth_state'] = $state;
        $_SESSION['scriptcat_login_referer'] = $referer;

        $authorizeUrl = $sc->authorizeUrl($redirectUri, 'openid', $state);
        dheader('Location: ' . $authorizeUrl);
        return;
    }

    // 回调: 校验 state 防止 CSRF
    ensureSession();
    $state = $_GET['state'] ?? '';
    $expectedState = $_SESSION['scriptcat_oauth_state'] ?? '';
    unset($_SESSION['scriptcat_oauth_state']);

    if (!$state || !$expectedState || $state !== $expectedState) {
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
        // 已绑定，直接登录
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

    // 事务写入绑定记录，防止并发竞态
    DB::query("BEGIN");
    try {
        // 再次检查是否已有绑定（双重检查）
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
        // 插入失败可能是唯一索引冲突，尝试查询已有绑定
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
 * 用 code 换取 ScriptCat 用户信息
 * 返回: {uid, username, email, avatar}
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

    ensureSession();
    $_SESSION['oauth_scriptcat_at'] = $resp['access_token'];
    $userinfo = $sc->userinfo($resp['access_token']);

    if (!$userinfo || !isset($userinfo['uid'])) {
        showError('获取用户信息失败,请反馈给网站管理员', 5);
    }

    return $userinfo;
}

/**
 * 创建 ScriptCat 客户端实例
 */
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

/**
 * 获取 ScriptCat OAuth 回调 URL
 */
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

    ensureSession();
    $referer = sanitizeReferer($_SESSION['scriptcat_login_referer'] ?? dreferer());
    unset($_SESSION['scriptcat_login_referer']);

    openMessage('登录成功,3秒后跳转', $referer ?: $_G['siteurl']);
}

/**
 * 根据 ScriptCat 用户信息自动创建 Discuz 用户
 */
function createDiscuzUserFromScriptcat($scriptcatUid, $username, $email)
{
    global $_G;

    // 检查用户名是否已被占用
    $existing = C::t('common_member')->fetch_by_username($username);
    if ($existing) {
        showError('用户名已被占用，请联系管理员', 5);
    }
    $finalUsername = $username;

    if (!$email) {
        showError('ScriptCat 账号未设置邮箱，请先设置邮箱后再登录', 5);
    }
    $password = generateRandomString(16);

    // 通过 UCenter 注册用户
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

    // 在 Discuz 中创建用户记录
    $groupid = $_G['setting']['newusergroupid'] ?? 10;
    C::t('common_member')->insert_user($ucUid, $finalUsername, '', $email, $_G['clientip'], $groupid, array('emailstatus' => 1));

    return $ucUid;
}
