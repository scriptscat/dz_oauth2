<?php

/**
 * ScriptCat OAuth 登录插件
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

    public function global_login_text()
    {
        return tpl_global_oauth_login_extra();
    }

    public function global_login_extra()
    {
        return tpl_global_oauth_login_extra();
    }

    public function global_usernav_extra()
    {
        return tpl_global_oauth_usernv_extra();
    }

}

class plugin_codfrm_oauth2_member extends plugin_codfrm_oauth2
{

    public function logging_input()
    {
        return tpl_global_oauth_usernv_extra();
    }
}
