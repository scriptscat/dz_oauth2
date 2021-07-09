<?php

if (!defined('IN_DISCUZ')) {
    exit('Access Denied');
}

$sql = <<<EOF

CREATE TABLE IF NOT EXISTS `pre_coauth_github` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uid` int(11) NOT NULL,
  `openid` varchar(128) NOT NULL,
  `name` varchar(128) NOT NULL,
  `createtime` bigint(20) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `openid` (`openid`) USING BTREE,
  UNIQUE KEY `uid` (`uid`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4;
      
EOF;

runquery($sql);
