<?php

if (!defined('IN_DISCUZ')) {
    exit('Access Denied');
}

$sql = <<<EOF

-- ScriptCat OAuth 账号绑定表，记录 Discuz 用户与 ScriptCat 账号的关联关系
CREATE TABLE IF NOT EXISTS `pre_oauth_scriptcat` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `uid` mediumint(11) NOT NULL COMMENT 'Discuz 用户UID',
  `openid` varchar(128) NOT NULL COMMENT 'ScriptCat 用户唯一标识',
  `name` varchar(128) NOT NULL COMMENT 'ScriptCat 用户名',
  `createtime` bigint(20) NOT NULL COMMENT '绑定时间戳',
  PRIMARY KEY (`id`),
  UNIQUE KEY `openid` (`openid`) USING BTREE,
  UNIQUE KEY `uid` (`uid`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;

EOF;

runquery($sql);

// 数据迁移：将 pre_common_member / pre_common_member_archive 中的用户批量写入绑定表
// 如果绑定表已有超过10条数据，说明已迁移过，跳过
$count = DB::result_first("SELECT COUNT(*) FROM %t", array('oauth_scriptcat'));
if ($count <= 10) {
    // 从主表迁移
    DB::query("INSERT IGNORE INTO %t (uid, openid, name, createtime) SELECT uid, uid, username, UNIX_TIMESTAMP() FROM %t WHERE uid NOT IN (SELECT uid FROM %t)", array('oauth_scriptcat', 'common_member', 'oauth_scriptcat'));

    // 从归档表迁移
    if (DB::fetch_first("SHOW TABLES LIKE '%t'", array('common_member_archive'))) {
        DB::query("INSERT IGNORE INTO %t (uid, openid, name, createtime) SELECT uid, uid, username, UNIX_TIMESTAMP() FROM %t WHERE uid NOT IN (SELECT uid FROM %t)", array('oauth_scriptcat', 'common_member_archive', 'oauth_scriptcat'));
    }
}

$finish = true;
