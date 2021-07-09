<?php

if (!defined('IN_DISCUZ')) {
    exit('Access Denied');
}

if (!$uid) {
    return;
}

global $_G;

C::t('#codfrm_oauth2#oauth_github')->insert(array(
    'uid' => $uid,
    'openid' => $_G['github_login_id'],
    'name' => $_G['github_login_name'],
    'createtime' => time()
));
