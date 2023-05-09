<?php

if (!defined('IN_DISCUZ')) {
    exit('Aecsse Denied');
}

class table_oauth_access_token extends discuz_table
{
    public function __construct()
    {

        $this->_table = 'coauth_access_token';
        $this->_pk = 'access_token';
        $this->_pre_cache_key = 'coauth_access_token';

        parent::__construct();
    }

    //TODO:计划清理到期access_token
    public function fetchByAccessToken($access_token)
    {
        return DB::fetch_first('select * from %t where access_token=%s', array($this->_table, $access_token));
    }


    public function create($access_token, $uid, $client_id, $scope)
    {
        return C::t('#codfrm_oauth2#oauth_access_token')->insert(array(
            'access_token' => $access_token,
            'client_id' => $client_id,
            'uid' => $uid,
            'scope' => $scope,
            'createtime' => time()
        ));
    }

    public function cleandue(number $time)
    {
        return DB::delete($this->_table, "createtime<$time");
    }
}

?>