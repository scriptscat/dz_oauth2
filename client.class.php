<?php

/**
 * oauth客户端
 */

if (!defined('IN_DISCUZ')) {
    exit('Access Denied');
}

class plugin_codfrm_oauth2
{

    public function global_login_extra()
    {
        return "login_ok1";
    }

}

class  plugin_codfrm_oauth2_member extends plugin_codfrm_oauth2
{

    public function logging_input()
    {
        return "12334";
    }

}