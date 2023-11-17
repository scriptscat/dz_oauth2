<?php

if (!defined('IN_DISCUZ')) {
    exit('Access Denied');
}

// 输出错误
ini_set('display_errors', 'on');
error_reporting(E_ALL);

require_once DISCUZ_ROOT . '/source/plugin/codfrm_oauth2/table/table_oauth_github.php';
require_once DISCUZ_ROOT . '/source/plugin/codfrm_oauth2/table/table_oauth_scriptcat.php';
require_once DISCUZ_ROOT . '/source/plugin/codfrm_oauth2/lib/github.php';
require_once DISCUZ_ROOT . '/source/plugin/codfrm_oauth2/lib/scriptcat.php';
require_once DISCUZ_ROOT . '/source/plugin/codfrm_oauth2/lib/utils.php';
require_once DISCUZ_ROOT . '/source/function/function_member.php';
require_once DISCUZ_ROOT . '/source/class/class_member.php';

global $_G;
$setting = $_G['cache']['plugin']['codfrm_oauth2'];

switch ($_GET['op']) {
    case 'redirect':
        handleRedirect();
        break;
    case 'bind':
        handleBind();
        break;
    case 'register':
        register();
        break;
    case 'bind2':
        handleBind2();
        break;
    case 'bind3':
        handleBind3();
        break;
    case 'unbind':
        handleUnbind();
        break;
    default:
        showError('错误的操作');
}

function handleRedirect()
{
    global $_GET;
    switch ($_GET['p']) {
        case 'github':
            handleGithubRedirect();
            break;
        default:
            showError('错误的请求');
    }
}

function handleBind()
{
    global $_G;

    if ($_G['uid']) {
        openMessage('登录成功', $_G['siteurl'], 'right', 3);
    }

    session_start();
    $resp = githubUser($_SESSION['oauth_github_at']);

    if (!$resp) {
        showError('系统网络错误,请反馈给网站管理员', 5);
    }

    if (!$resp['login']) {
        showError("错误:{describe}", 5, ['describe' => $resp['describe']]);
    }
    if ($_G['member']) {
        $_G['member']['freeze'] = '';
    }
    require_once template("codfrm_oauth2:bind", $resp);
}

function handleBind2()
{
    global $_G;

    $resp = getGithubUserInfo();
    $_G['github_login_id'] = $resp['id'];
    $_G['github_login_name'] = $resp['name'] ?? $resp['login'];

    $ctl_obj = new logging_ctl();
    $_G['setting']['seccodestatus'] = 0;

    $ctl_obj->extrafile = DISCUZ_ROOT . '/source/plugin/codfrm_oauth2/lib/bind.php';
    $ctl_obj->template = 'member/login';
    $ctl_obj->on_login();
}

function handleBind3()
{
    global $_G;

    if (!$_G['uid']) {
        openMessage('账号未登录', $_G['siteurl']);
    }

    switch ($_GET['p']) {
        case "github":
            handleGithubBind3();
            break;
        case "scriptcat":
            handleScriptcatBind3();
    }
}

function handleScriptcatBind3()
{
    global $_G;

    $table = new table_oauth_scriptcat();
    $raw = $table->fetchByUid($_G['uid']);
    if ($raw) {
        showError('已绑定脚本猫的工具箱账号', 5);
        return;
    }

    $resp = fetchScriptcat($_GET['code']);
    if (!$resp) {
        showError('系统网络错误,请反馈给网站管理员', 5);
    }

    $raw = $table->fetchByScriptcat($resp['data']['user_id']);
    if ($raw) {
        showError('此脚本猫账号已经绑定过其它的账号了', 5);
    }


    $table->insert([
        'uid' => $_G['uid'],
        'openid' => $resp['data']['user_id'],
        'name' => $resp['data']['username'],
        'createtime' => time(),
    ]);

    openMessage('绑定成功', $_G['siteurl'] . '/home.php?mod=spacecp&ac=plugin&id=codfrm_oauth2:spacecp', 'right', 3);

}

function fetchScriptcat($code)
{
    global $_G;
    $setting = $_G['cache']['plugin']['codfrm_oauth2'];

    if (!$code) {
        showError('错误请求', 3);
    }

    $sc = new ScriptCat($setting['scriptcat_oauth_client_id'], $setting['scriptcat_oauth_client_secret']);
    $resp = $sc->accessToken($code, 'bind3');
    if (!$resp) {
        showError('系统网络错误,请反馈给网站管理员', 5);
    }

    if (!$resp['access_token']) {
        showError('系统错误,请反馈给网站管理员:{message}', 5, ['message' => $resp['error_description']]);
    }

    session_start();
    $_SESSION['oauth_github_at'] = $resp['access_token'];
    $resp = $sc->userinfo($resp['access_token']);

    if (!$resp) {
        showError('系统网络错误,请反馈给网站管理员', 5);
    }

    if ($resp['code'] !== 0) {
        showError('错误:{describe}', 5, ['describe' => $resp['msg']]);
    }

    return $resp;
}

function handleGithubBind3()
{
    global $_G, $_GET;
    $table = new table_oauth_github();
    $raw = $table->fetchByUid($_G['uid']);
    if ($raw) {
        showError('此账号已经绑定过GitHub了', 5);
    }

    $resp = fetchGithub($_GET['code']);
    if (!$resp) {
        showError('系统网络错误,请反馈给网站管理员', 5);
    }
    $raw = $table->fetchByGithub($resp['id']);

    if ($raw) {
        showError('此GitHub已经绑定过其它的账号了', 5);
    }

    C::t('#codfrm_oauth2#oauth_github')->insert(array(
        'uid' => $_G['uid'],
        'openid' => $resp['id'],
        'name' => $resp['name'] ?? $resp['login'],
        'createtime' => time()
    ));

    openMessage('绑定成功', $_G['siteurl'] . '/home.php?mod=spacecp&ac=plugin&id=codfrm_oauth2:spacecp', 'right', 3);
}

function handleUnbind()
{
    global $_G;

    if (!$_G['uid']) {
        openMessage('账号未登录', $_G['siteurl'], 'right', 3);
    }


    switch ($_GET['p']) {
        case "github":
            $table = new table_oauth_github();
            $raw = $table->fetchByUid($_G['uid']);

            if (!$raw) {
                showError('没有绑定GitHub账号', 5);
            }

            if (time() < $raw['createtime'] + 86400 * 60) {
                openMessage('绑定60天后才能解除绑定', $_G['siteurl'] . '/home.php?mod=spacecp&ac=plugin&id=codfrm_oauth2:spacecp', 'error', 3);
            }

            C::t('#codfrm_oauth2#oauth_github')->delete($raw['id']);
            openMessage('解绑成功', $_G['siteurl'] . '/home.php?mod=spacecp&ac=plugin&id=codfrm_oauth2:spacecp', 'right', 3);
        case "scriptcat":
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
}

function handleGithubRedirect()
{
    global $setting;

    if (!$setting['github_oauth_client_id']) {
        showError('当前站点暂未设置GitHub登录方式', 3);
    }

    github();
}

function fetchGithub($code)
{
    global $_G;
    $setting = $_G['cache']['plugin']['codfrm_oauth2'];

    if (!$code) {
        showError('错误请求', 3);
    }

    $resp = githubAccessToken($setting['github_oauth_client_id'], $setting['github_oauth_secret'], $code);

    if (!$resp) {
        showError('系统网络错误,请反馈给网站管理员', 5);
    }

    if (!$resp['access_token']) {
        showError('系统错误,请反馈给网站管理员:{message}', 5, ['message' => $resp['error_description']]);
    }

    session_start();
    $_SESSION['oauth_github_at'] = $resp['access_token'];
    $resp = githubUser($resp['access_token']);

    if (!$resp) {
        showError('系统网络错误,请反馈给网站管理员', 5);
    }

    if (!$resp['login']) {
        showError('错误:{describe}', 5, ['describe' => $resp['describe']]);
    }

    return $resp;
}

function github()
{
    global $_G;

    $resp = fetchGithub($_GET['code']);
    $table = new table_oauth_github();
    $raw = $table->fetchByGithub($resp['id']);

    if (!$raw) {
        dheader('Location:' . ($_G['siteurl'] . 'plugin.php?id=codfrm_oauth2:bind&op=bind'));
    } else {
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
        if (!($member = getuserbyuid($raw['uid'], 1))) {
            showError('用户不存在', 5);
        }

        $cookietime = 1296000;
        setloginstatus($member, $cookietime);
        openMessage('登录成功,3秒后跳转', $_GET['referer'] ?? dreferer());
    }
}

function getGithubUserInfo()
{
    session_start();
    $resp = githubUser($_SESSION['oauth_github_at']);

    if (!$resp) {
        showError('系统网络错误,请反馈给网站管理员', 5);
    }

    if (!$resp['login']) {
        showError('错误:{describe}', 5, ['describe' => $resp['describe']]);
    }

    return $resp;
}

function register()
{
    global $_G;

    $resp = getGithubUserInfo();
    $_G['github_login_id'] = $resp['id'];
    $_G['github_login_name'] = $resp['name'] ?? $resp['login'];

    $table = new table_oauth_github();
    $raw = $table->fetchByGithub($resp['id']);

    if ($raw) {
        showError('已经绑定账号了，请重新登录', 5);
    }

    $ctl_obj = new register_ctl();
    $setting = $_G['setting'];
    $setting['reginput'] = [
        'username' => 'username',
        'password' => 'password',
        'password2' => 'password2',
        'email' => 'email',
    ];
    $ctl_obj->setting = $setting;

    $_G['setting']['seccodedata']['rule']['register']['allow'] = 3;
    $_G['setting']['secqaa']['status'] = 0;

    $ctl_obj->setting['ignorepassword'] = 1;
    $ctl_obj->setting['checkuinlimit'] = 1;
    $ctl_obj->setting['strongpw'] = 0;
    $ctl_obj->setting['pwlength'] = 0;
    $ctl_obj->extrafile = DISCUZ_ROOT . '/source/plugin/codfrm_oauth2/lib/bind.php';
    $ctl_obj->template = 'member/register';
    $ctl_obj->on_register();
}
