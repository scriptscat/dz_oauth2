<?php


/**
 * 重定向判断
 */

if (!defined('IN_DISCUZ')) {
    exit('Access Denied');
}

require_once DISCUZ_ROOT . '/source/plugin/codfrm_oauth2/table/table_oauth_github.php';
require_once DISCUZ_ROOT . '/source/plugin/codfrm_oauth2/lib/github.php';
require_once DISCUZ_ROOT . '/source/function/function_member.php';
require_once DISCUZ_ROOT . '/source/class/class_member.php';

global $_G;
$setting = $_G['cache']['plugin']['codfrm_oauth2'];

switch ($_GET['op']) {
    case 'redirect':
        switch ($_GET['p']) {
            case 'github':
                if (!$setting['github_oauth_client_id']) {
                    return showmessage('当前站点暂未设置GitHub登录方式', dreferer(), [], ['alert' => 'error', 'refreshtime' => 3, 'referer' => rawurlencode(dreferer())]);
                }
                github();
                break;
            default:
                return showmessage('错误的请求', dreferer(), [], ['alert' => 'error', 'refreshtime' => 3, 'referer' => rawurlencode(dreferer())]);
        }
        break;
    case 'bind':
        if ($_G['uid']) {
            return showmessage('登录成功', $_G['siteurl'], [], ['alert' => 'right', 'refreshtime' => 3]);
        }
        session_start();
        $resp = githubUser($_SESSION['oauth_github_at']);
        if (!$resp) {
            return showmessage('系统网络错误,请反馈给网站管理员', dreferer(), [], ['alert' => 'error', 'refreshtime' => 5, 'referer' => rawurlencode(dreferer())]);
        }
        if (!$resp['login']) {
            return showmessage('错误:{describe}', dreferer(), ['describe' => $resp['describe']], ['alert' => 'error', 'refreshtime' => 5, 'referer' => rawurlencode(dreferer())]);
        }

        require_once template("codfrm_oauth2:bind", $resp);
        break;
    case 'register':
        register();
        break;
    case 'bind2':

        $resp = getGithubUserInfo();
        $_G['github_login_id'] = $resp['id'];
        $_G['github_login_name'] = $resp['name'];

        $ctl_obj = new logging_ctl();
        $_G['setting']['seccodestatus'] = 0;

        $ctl_obj->extrafile = DISCUZ_ROOT . '/source/plugin/codfrm_oauth2/lib/bind.php';
        $ctl_obj->template = 'member/login';
        $ctl_obj->on_login();

        break;
    case 'bind3':
        if (!$_G['uid']) {
            return showmessage('账号未登录', $_G['siteurl'], [], ['alert' => 'right', 'refreshtime' => 3]);
        }
        $resp = fetchGithub($_GET['code']);
        $table = new table_oauth_github();
        $raw = $table->fetchByGithub($resp['id']);
        if ($raw) {
            return showmessage('此GitHub已经绑定过其它的账号了', dreferer(), [], ['alert' => 'error', 'refreshtime' => 5, 'referer' => rawurlencode(dreferer())]);
        }
        C::t('#codfrm_oauth2#oauth_github')->insert(array(
            'uid' => $_G['uid'],
            'openid' => $resp['id'],
            'name' => $resp['name'],
            'createtime' => time()
        ));
        return showmessage('绑定成功', $_G['siteurl'] . '/home.php?mod=spacecp&ac=plugin&id=codfrm_oauth2:spacecp', [], ['alert' => 'right', 'refreshtime' => 3]);
    case 'unbind':
        if (!$_G['uid']) {
            return showmessage('账号未登录', $_G['siteurl'], [], ['alert' => 'right', 'refreshtime' => 3]);
        }
        $table = new table_oauth_github();
        $raw = $table->fetchByUid($_G['uid']);
        if (!$raw) {
            return showmessage('没有绑定GitHub账号', dreferer(), [], ['alert' => 'error', 'refreshtime' => 5, 'referer' => rawurlencode(dreferer())]);
        }
        if (time() < $raw['createtime'] + 86400 * 60) {
            return showmessage('绑定60天后才能解除绑定', $_G['siteurl'] . '/home.php?mod=spacecp&ac=plugin&id=codfrm_oauth2:spacecp', [], ['alert' => 'error', 'refreshtime' => 3]);
        }
        C::t('#codfrm_oauth2#oauth_github')->delete($raw['id']);
        return showmessage('解绑成功', $_G['siteurl'] . '/home.php?mod=spacecp&ac=plugin&id=codfrm_oauth2:spacecp', [], ['alert' => 'right', 'refreshtime' => 3]);
    default:
        return showmessage('错误的操作', dreferer(), [], ['alert' => 'error', 'refreshtime' => 3, 'referer' => rawurlencode(dreferer())]);
}

function fetchGithub($code)
{
    global $_G;
    $setting = $_G['cache']['plugin']['codfrm_oauth2'];
    if (!$code) {
        return showmessage('错误请求', dreferer(), [], ['alert' => 'error', 'refreshtime' => 3, 'referer' => rawurlencode(dreferer())]);
    }
    $resp = githubAccessToken($setting['github_oauth_client_id'], $setting['github_oauth_secret'], $code);
    if (!$resp) {
        return showmessage('系统网络错误,请反馈给网站管理员', dreferer(), [], ['alert' => 'error', 'refreshtime' => 5, 'referer' => rawurlencode(dreferer())]);
    }
    if (!$resp['access_token']) {
        return showmessage(('系统错误,请反馈给网站管理员:{message}'), dreferer(), ['message' => $resp['error_description']], ['alert' => 'error', 'refreshtime' => 5, 'referer' => rawurlencode(dreferer())]);
    }
    //NOTE: 直接存的session,以后优化吧
    session_start();
    $_SESSION['oauth_github_at'] = $resp['access_token'];
    $resp = githubUser($resp['access_token']);
    if (!$resp) {
        return showmessage('系统网络错误,请反馈给网站管理员', dreferer(), [], ['alert' => 'error', 'refreshtime' => 5, 'referer' => rawurlencode(dreferer())]);
    }
    if (!$resp['login']) {
        return showmessage('错误:{describe}', dreferer(), ['describe' => $resp['describe']], ['alert' => 'error', 'refreshtime' => 5, 'referer' => rawurlencode(dreferer())]);
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
        //去注册
        dheader('Location:' . ($_G['siteurl'] . 'plugin.php?id=codfrm_oauth2:bind&op=bind'));
    } else {
        // 去登录
        require_once libfile('function/member');
        require_once libfile('function/core');

        if (!($member = getuserbyuid($raw['uid'], 1))) {
            return showmessage('用户不存在', dreferer(), [], ['alert' => 'error', 'refreshtime' => 5, 'referer' => rawurlencode(dreferer())]);
        }

        $cookietime = 1296000;
        setloginstatus($member, $cookietime);


        return showmessage('登录成功,3秒后跳转', dreferer(), [], ['alert' => 'right', 'refreshtime' => 3, 'referer' => rawurlencode(dreferer())]);
    }
}

function getGithubUserInfo()
{
    session_start();
    $resp = githubUser($_SESSION['oauth_github_at']);
    if (!$resp) {
        return showmessage('系统网络错误,请反馈给网站管理员', dreferer(), [], ['alert' => 'error', 'refreshtime' => 5, 'msgtype' => 3, 'referer' => rawurlencode(dreferer())]);
    }
    if (!$resp['login']) {
        return showmessage('错误:{describe}', dreferer(), ['describe' => $resp['describe']], ['alert' => 'error', 'refreshtime' => 5, 'msgtype' => 3, 'referer' => rawurlencode(dreferer())]);
    }
    return $resp;
}

function register()
{
    global $_G;

    $resp = getGithubUserInfo();
    $_G['github_login_id'] = $resp['id'];
    $_G['github_login_name'] = $resp['name'];

    $table = new table_oauth_github();
    $raw = $table->fetchByGithub($resp['id']);
    if ($raw) {
        return showmessage('已经绑定账号了，请重新登录', dreferer(), ['describe' => $resp['describe']], ['alert' => 'error', 'refreshtime' => 5, 'msgtype' => 3, 'referer' => rawurlencode(dreferer())]);
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
    $ctl_obj->extrafile = DISCUZ_ROOT . '/source/plugin/codfrm_oauth2/lib/bing.php';
    $ctl_obj->template = 'member/register';
    $ctl_obj->on_register();

}