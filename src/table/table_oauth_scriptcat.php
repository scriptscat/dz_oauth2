<?php

if (!defined('IN_DISCUZ')) {
    exit('Aecsse Denied');
}

class table_oauth_scriptcat extends discuz_table
{
    public function __construct()
    {

        $this->_table = 'coauth_scriptcat';
        $this->_pk = 'id';
        $this->_pre_cache_key = 'coauth_scriptcat_';

        parent::__construct();
    }


    public function fetchByScriptcat($scriptcat)
    {
        return DB::fetch_first('select * from %t where openid=%s', array($this->_table, $scriptcat));
    }

    public function fetchByUid($uid)
    {
        return DB::fetch_first('select * from %t where uid=%s', array($this->_table, $uid));
    }

}

?>