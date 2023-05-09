<?php

if (!defined('IN_DISCUZ')) {
    exit('Access Denied');
}

require_once libfile('table/oauth_github', 'plugin/codfrm_oauth2');

global $_G;
$table = new table_oauth_github();
$github = $table->fetchByUid($_G['uid']);

require_once libfile('table/oauth_scriptcat', 'plugin/codfrm_oauth2');

$table = new table_oauth_scriptcat();
$scriptcat = $table->fetchByUid($_G['uid']);

