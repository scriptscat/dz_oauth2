<?php

global $_G;
require_once DISCUZ_ROOT . '/source/plugin/codfrm_oauth2/table/table_oauth_pushcat.php';
switch ($_GET['op']) {
    case "set":
        // 添加pushcat access_key 和 tag记录
        $table = new table_oauth_pushcat();
        $raw = $table->fetchByUid($_G['uid']);
        if (!$raw) {
            // 创建
            C::t('#codfrm_oauth2#oauth_pushcat')->insert(array(
                'uid' => $_G['uid'],
                'access_key' => $_POST['access_key'] ?? "",
                'tags' => $_POST['tags'] ?? "",
                'createtime' => time()
            ));
        } else {
            // 更新
            C::t('#codfrm_oauth2#oauth_pushcat')->update($raw['id'], array(
                'access_key' => $_POST['access_key'] ?? '',
                'tags' => $_POST['tags'] ?? "",
                'createtime' => time()
            ));
        }
        break;
    case "test":
        $table = new table_oauth_pushcat();
        $raw = $table->fetchByUid($_G['uid']);
        if (!$raw) {
            echo json_encode(['code' => -1, 'msg' => '记录不存在']);
            return;
        }
        require_once DISCUZ_ROOT . '/source/plugin/codfrm_oauth2/lib/scriptcat.php';
        $pushcat = new ScriptCat($raw['access_key']);
        $res = $pushcat->send(
            "油猴中文网测试信息",
            "测试消息内容,[油猴中文网](https://bbs.tampermonkey.net.cn/)", [
            'tags' => str_split($raw['tags'], ',')
        ]);
        echo $res;
        break;
}