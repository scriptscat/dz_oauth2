<?php

if (!defined('IN_DISCUZ')) {
    exit('Access Denied');
}

$sql = <<<EOF

CREATE TABLE IF NOT EXISTS `pre_coauth_client` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `client_id` varchar(128) NOT NULL,
  `client_secret` varchar(128) NOT NULL,
  `scope` varchar(255) NOT NULL,
  `website` varchar(255) NOT NULL,
  `site_logo` varchar(255) NOT NULL,
  `redirect_uri` varchar(255) NOT NULL,
  `createtime` bigint(20) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `client_id` (`client_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4;
      
CREATE TABLE IF NOT EXISTS `pre_coauth_code` (
  `code` varchar(128) CHARACTER SET latin1 NOT NULL,
  `uid` int(11) NOT NULL,
  `client_id` varchar(128) CHARACTER SET latin1 NOT NULL,
  `scope` varchar(255) CHARACTER SET latin1 NOT NULL,
  `createtime` bigint(20) NOT NULL,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `pre_coauth_access_token` (
  `access_token` varchar(128) CHARACTER SET latin1 NOT NULL,
  `uid` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `scope` varchar(255) CHARACTER SET latin1 NOT NULL,
  `createtime` bigint(20) NOT NULL,
  PRIMARY KEY (`access_token`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `pre_coauth_record` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `scope` varchar(255) NOT NULL,
  `createtime` bigint(20) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `client_id_uid` (`client_id`,`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

EOF;

runquery($sql);

$finish = true;
