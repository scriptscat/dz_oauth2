<?php

if (!defined('IN_DISCUZ')) {
    exit('Aecsse Denied');
}

class table_oauth_code extends discuz_table
{
    public function __construct()
    {

        $this->_table = 'coauth_code';
        $this->_pk = 'code';
        $this->_pre_cache_key = 'coauth_code_';

        parent::__construct();
    }


    public function fetchByCode($code)
    {
        return DB::fetch_first('select * from %t where code=%s', array($this->_table, $code));
    }

    public function create($code, $uid, $client_id, $scope)
    {
        return C::t('#codfrm_oauth2#oauth_code')->insert(array(
            'code' => $code,
            'client_id' => $client_id,
            'uid' => $uid,
            'scope' => $scope,
            'createtime' => time()
        ));
    }

}

?>