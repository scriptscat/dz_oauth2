<?php

if (!defined('IN_DISCUZ')) {
    exit('Access Denied');
}

global $_G;
require_once DISCUZ_ROOT . '/source/plugin/codfrm_oauth2/table/table_oauth_scriptcat.php';
require_once DISCUZ_ROOT . '/source/plugin/codfrm_oauth2/table/table_pushcat_subscribe.php';
require_once DISCUZ_ROOT . '/source/plugin/codfrm_oauth2/lib/utils.php';

if (!$_G['uid']) {
    showError('请先登录');
}

switch ($_GET['op']) {
    case "subscribe":
        handleSubscribe();
        break;
    case "unsubscribe":
        handleUnsubscribe();
        break;
}

function handleSubscribe()
{
    global $_G;
    $tid = $_GET['tid'];
    if (!$tid) {
        showError('缺少参数');
    }
    $table = new table_pushcat_subscribe();
    $raw = $table->fetchByUidTid($_G['uid'], $tid);
    if ($raw) {
        if ($raw['status'] == 1) {
            showError('已经订阅了');
        }
        $table->updateStatus($raw['id'], 1);
        openMessage('订阅成功', dreferer());
    }
    $table->create($_G['uid'], $tid);
    openMessage('订阅成功', dreferer());
}

function handleUnsubscribe()
{
    global $_G;
    $tid = $_GET['tid'];
    if (!$tid) {
        showError('缺少参数');
    }
    $table = new table_pushcat_subscribe();
    $raw = $table->fetchByUidTid($_G['uid'], $tid);
    if (!$raw) {
        showError('没有订阅');
    }
    if ($raw['status'] == 0) {
        showError('没有订阅');
    }
    $table->updateStatus($raw['id'], 0);
    openMessage('取消订阅成功', dreferer());
}