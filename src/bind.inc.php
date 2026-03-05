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

switch ($_GET['op']) {
    case 'redirect':
        handleScriptcatRedirect();
        break;
    case 'bind3':
        handleScriptcatBind3();
        break;
    case 'unbind':
        handleUnbind();
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
        $referer = $_GET['referer'] ?? dreferer();
        session_start();
        $_SESSION['scriptcat_login_referer'] = $referer;
        $authorizeUrl = $sc->authorizeUrl($redirectUri, 'openid');
        dheader('Location: ' . $authorizeUrl);
        return;
    }

    // 回调: 带 code 参数，换取用户信息
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

    // 2. 检查是否有相同 uid 的 Discuz 用户（迁移用户 uid 一致）
    $member = getuserbyuid($scriptcatUid, 1);
    if ($member) {
        // 自动创建绑定关系并登录
        $table->insert([
            'uid' => $member['uid'],
            'openid' => $scriptcatUid,
            'name' => $username,
            'createtime' => time(),
        ]);
        scriptcatAutoLogin($member['uid']);
        return;
    }

    // 3. 没有对应的 Discuz 用户，自动创建
    $newUid = createDiscuzUserFromScriptcat($scriptcatUid, $username, $email);
    if ($newUid) {
        $table->insert([
            'uid' => $newUid,
            'openid' => $scriptcatUid,
            'name' => $username,
            'createtime' => time(),
        ]);
        scriptcatAutoLogin($newUid);
        return;
    }

    showError('创建账号失败，请联系管理员', 5);
}

/**
 * 已登录用户绑定 ScriptCat 账号
 */
function handleScriptcatBind3()
{
    global $_G;

    if (!$_G['uid']) {
        openMessage('账号未登录', $_G['siteurl']);
    }

    $table = new table_oauth_scriptcat();
    $raw = $table->fetchByUid($_G['uid']);
    if ($raw) {
        showError('已绑定脚本猫账号', 5);
        return;
    }

    $code = $_GET['code'] ?? '';
    if (!$code) {
        // 第一次访问: 重定向到 ScriptCat 授权页
        $sc = newScriptCatClient();
        $redirectUri = getScriptcatRedirectUri('bind3');
        $authorizeUrl = $sc->authorizeUrl($redirectUri, 'openid');
        dheader('Location: ' . $authorizeUrl);
        return;
    }

    $resp = fetchScriptcatUserInfo($code, 'bind3');
    if (!$resp) {
        showError('系统网络错误,请反馈给网站管理员', 5);
    }

    $raw = $table->fetchByScriptcat($resp['uid']);
    if ($raw) {
        showError('此脚本猫账号已经绑定过其它的账号了', 5);
    }

    $table->insert([
        'uid' => $_G['uid'],
        'openid' => $resp['uid'],
        'name' => $resp['username'],
        'createtime' => time(),
    ]);

    openMessage('绑定成功', $_G['siteurl'] . '/home.php?mod=spacecp&ac=plugin&id=codfrm_oauth2:spacecp', 'right', 3);
}

function handleUnbind()
{
    global $_G;

    if (!$_G['uid']) {
        openMessage('账号未登录', $_G['siteurl'], 'right', 3);
    }

    $table = new table_oauth_scriptcat();
    $raw = $table->fetchByUid($_G['uid']);

    if (!$raw) {
        showError('没有绑定脚本猫账号', 5);
    }

    if (time() < $raw['createtime'] + 86400 * 60) {
        openMessage('绑定60天后才能解除绑定', $_G['siteurl'] . '/home.php?mod=spacecp&ac=plugin&id=codfrm_oauth2:spacecp', 'error', 3);
    }

    C::t('#codfrm_oauth2#oauth_scriptcat')->delete($raw['id']);
    openMessage('解绑成功', $_G['siteurl'] . '/home.php?mod=spacecp&ac=plugin&id=codfrm_oauth2:spacecp', 'right', 3);
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
        showError('系统错误,请反馈给网站管理员:{message}', 5, ['message' => $resp['error_description'] ?? $resp['message'] ?? '未知错误']);
    }

    session_start();
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

    session_start();
    $referer = $_SESSION['scriptcat_login_referer'] ?? dreferer();
    unset($_SESSION['scriptcat_login_referer']);

    openMessage('登录成功,3秒后跳转', $referer ?: $_G['siteurl']);
}

/**
 * 根据 ScriptCat 用户信息自动创建 Discuz 用户
 */
function createDiscuzUserFromScriptcat($scriptcatUid, $username, $email)
{
    global $_G;

    // 检查用户名是否已被占用，如果是则加后缀
    $finalUsername = $username;
    $existing = C::t('common_member')->fetch_by_username($finalUsername);
    if ($existing) {
        $finalUsername = $username . '_' . rand(1000, 9999);
        $existing = C::t('common_member')->fetch_by_username($finalUsername);
        if ($existing) {
            $finalUsername = $username . '_' . rand(10000, 99999);
        }
    }

    // 检查邮箱是否已存在
    if ($email) {
        $existingEmail = C::t('common_member')->fetch_by_email($email);
        if ($existingEmail) {
            $email = '';
        }
    }

    $data = array(
        'username' => $finalUsername,
        'password' => '',
        'email' => $email ?: $finalUsername . '@scriptcat.placeholder',
        'adminid' => 0,
        'groupid' => $_G['setting']['newusergroupid'] ?? 10,
        'regdate' => $_G['timestamp'],
        'credits' => 0,
        'timeoffset' => '9999',
    );

    $uid = C::t('common_member')->insert($data, true);
    if (!$uid) {
        return false;
    }

    C::t('common_member_count')->insert($uid);
    C::t('common_member_status')->insert(array(
        'uid' => $uid,
        'regip' => $_G['clientip'],
        'lastip' => $_G['clientip'],
        'lastvisit' => $_G['timestamp'],
        'lastactivity' => $_G['timestamp'],
        'lastpost' => 0,
    ));
    C::t('common_member_profile')->insert(array('uid' => $uid));
    C::t('common_member_field_forum')->insert(array('uid' => $uid));
    C::t('common_member_field_home')->insert(array('uid' => $uid));

    return $uid;
}
