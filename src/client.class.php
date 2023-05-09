<?php

/**
 * oauth客户端,支持github
 */

if (!defined('IN_DISCUZ')) {
    exit('Access Denied');
}

include_once template('codfrm_oauth2:module');

require_once DISCUZ_ROOT . '/source/plugin/codfrm_oauth2/table/table_pushcat_subscribe.php';
require_once DISCUZ_ROOT . '/source/plugin/codfrm_oauth2/table/table_oauth_scriptcat.php';
require_once DISCUZ_ROOT . '/source/plugin/codfrm_oauth2/lib/scriptcat.php';

class plugin_codfrm_oauth2
{

    function __construct()
    {
    }

    public function global_login_text()
    {
        return tpl_global_oauth_login_extra();
    }

    public function global_login_extra()
    {
        return tpl_global_oauth_login_extra();
    }
}

class plugin_codfrm_oauth2_member extends plugin_codfrm_oauth2
{

    public function logging_input()
    {
        global $_G;

        return tpl_global_oauth_usernv_extra();
    }
}

class plugin_codfrm_oauth2_forum extends plugin_codfrm_oauth2
{
    public function viewthread_postfooter()
    {
        global $_G;
        // 判断是否订阅了
        $table = new table_pushcat_subscribe();
        $raw = $table->fetchByUidTid($_G['uid'], $_G['tid']);
        if ($raw && $raw['status'] == 1) {
            return ["<a href='/plugin.php?id=codfrm_oauth2:pushcat&op=unsubscribe&tid=$_G[tid]'>
<div style='display: inline-flex;align-items: center;'>
<img src='/source/plugin/codfrm_oauth2/image/rss.svg' height='16px' /><span>取消订阅</span>
</div>
</a>"];
        }

        return ["<a href='/plugin.php?id=codfrm_oauth2:pushcat&op=subscribe&tid=$_G[tid]'>
<div style='display: inline-flex;align-items: center;'>
<img src='/source/plugin/codfrm_oauth2/image/rss.svg' height='16px' /><span>订阅</span>
</div>
</a>"];
    }

    public function post_middle()
    {
        // 非post直接返回
//        if($_SERVER['REQUEST_METHOD'] != 'POST') {
//            return;
//        }
        // 查询出订阅用户
        global $_G;
        $setting = $_G['cache']['plugin']['codfrm_oauth2'];
        if (!$setting['scriptcat_oauth_client_id']) {
            return;
        }

        $table = new table_pushcat_subscribe();
        $raws = $table->fetchByTid($_G['tid']);
        // 查询出scriptcat的user_id然后推送
        $table = new table_oauth_scriptcat();
        $userIds = [];
        foreach ($raws as $raw) {
            $scriptcat = $table->fetchByUid($raw['uid']);
            if ($scriptcat) {
                $userIds[] = $scriptcat['openid'];
            }
        }
        $scriptcat = new ScriptCat(
            $setting['scriptcat_oauth_client_id'], $setting['scriptcat_oauth_client_secret'],
            "http://192.168.104.123:8080");

        $scriptcat->send($userIds, $_POST['subject'], $_POST['message']);

    }
}