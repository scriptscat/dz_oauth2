<?php

if (!defined('IN_DISCUZ')) {
    exit('Aecsse Denied');
}

class table_oauth_github extends discuz_table
{
    public function __construct()
    {

        $this->_table = 'coauth_github';
        $this->_pk = 'id';
        $this->_pre_cache_key = 'coauth_github_';

        parent::__construct();
    }


    public function fetchByGithub($github)
    {
        return DB::fetch_first('select * from %t where openid=%s', array($this->_table, $github));
    }

    public function fetchByUid($uid)
    {
        return DB::fetch_first('select * from %t where uid=%s', array($this->_table, $uid));
    }

}

?>