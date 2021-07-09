<?php

/**
 * oauth客户端,支持github
 */

if (!defined('IN_DISCUZ')) {
    exit('Access Denied');
}

include_once template('codfrm_oauth2:module');

class plugin_codfrm_oauth2
{

    function __construct()
    {
    }

    public function global_login_extra()
    {

        return tpl_global_oauth_login_extra();
    }
}

class  plugin_codfrm_oauth2_member extends plugin_codfrm_oauth2
{

    public function logging_input()
    {
        global $_G;
        $setting = $_G['cache']['plugin']['codfrm_oauth2'];

        return tpl_global_oauth_usernv_extra();
    }
}
