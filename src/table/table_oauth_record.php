<?php

if (!defined('IN_DISCUZ')) {
    exit('Aecsse Denied');
}

class table_oauth_record extends discuz_table
{
    public function __construct()
    {

        $this->_table = 'coauth_record';
        $this->_pk = 'id';
        $this->_pre_cache_key = 'coauth_record_';

        parent::__construct();
    }


    public function fetchByUid($client_id, $uid)
    {
        return DB::fetch_first('select * from %t where client_id=%d and uid=%d', array($this->_table, $client_id, $uid));
    }

    public function create($client_id, $uid, $scope)
    {
        return C::t('#codfrm_oauth2#oauth_record')->insert(array(
            'client_id' => $client_id,
            'uid' => $uid,
            'scope' => $scope,
            'createtime' => time()
        ));
    }
}

?>