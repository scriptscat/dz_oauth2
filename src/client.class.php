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

    public function post_middle_output()
    {
        // 非post直接返回
        // reply newthread
        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            return;
        }

        global $_G;
        $setting = $_G['cache']['plugin']['codfrm_oauth2'];
        if (!$setting['scriptcat_oauth_client_id']) {
            return;
        }

        if (empty($_G["messageparam"])) {
            return;
        }

        if ($_G["messageparam"][0] !== "post_reply_succeed") {
            return;
        }

        // 查询出订阅用户
        $userIds = [];
        $title = "";
        $content = "";
        switch ($_GET['action']) {
            case "reply":
                // 查询帖子标题
                $title = $_G['forum_thread']['subject'] . "有新的回复";
                $url = $_G['siteurl'] . 'forum.php?mod=viewthread&tid=' . $_G['tid'] . '&extra=';
                $content = "您发布在$_G[bbname]的帖子有新回复，[点我查看](" . $url . ") 链接: " . $url .
                    ("\n$_POST[message]");
                // 判断是否回复某人
                if ($_POST["reppid"]) {
                    // 通过pid查询回复的人
                    $userIds[] = C::t('forum_post')->fetch('tid:' . $_GET['tid'], $_POST["reppid"], true)["authorid"];
                }
                break;
            default:
                return;
        }

        $table = new table_pushcat_subscribe();
        $raws = $table->fetchByTid($_G['tid']);
        // 查询出scriptcat的user_id然后推送
        $table = new table_oauth_scriptcat();
        $openIds = [];
        foreach ($raws as $raw) {
            $userIds[] = $raw['uid'];
        }
        // userId去重
        $userIds = array_unique($userIds);
        foreach ($userIds as $userId) {
            // 忽略发布人
            if ($userId == $_G['uid']) {
                continue;
            }
            $scriptcat = $table->fetchByUid($userId);
            if ($scriptcat) {
                $openIds[] = $scriptcat['openid'];
            }
        }
        if ($openIds == []) {
            return;
        }
        try {
            $scriptcat = new ScriptCat(
                $setting['scriptcat_oauth_client_id'], $setting['scriptcat_oauth_client_secret']);
            $scriptcat->send($openIds, $title, $content, ['url' =>
                $_G['siteurl'] . '/forum.php?mod=viewthread&tid=' . $_G['tid'] . '&extra=']);
        } catch (Exception $e) {
            // 屏蔽错误
        }
    }

    public function post_message($param)
    {
        global $_G;
        try {
            // 判断自己有没有绑定脚本猫的工具箱
            if ($param['param'][0] == "post_newthread_succeed") {
                $table = new table_oauth_scriptcat();
                $scriptcat = $table->fetchByUid($_G['uid']);
                if (!$scriptcat) {
                    return;
                }
                // 有的话绑定
                $table = new table_pushcat_subscribe();
                $table->create($_G['uid'], $param['param'][2]['tid']);
            }
        } catch (Exception $e) {
            // 屏蔽错误
        }
    }
}


class mobileplugin_codfrm_oauth2_forum extends plugin_codfrm_oauth2_forum
{

}