<?php

if (!defined('IN_DISCUZ')) {
    exit('Aecsse Denied');
}

class table_oauth_client extends discuz_table
{
    public function __construct()
    {

        $this->_table = 'coauth_client';
        $this->_pk = 'id';
        $this->_pre_cache_key = 'coauth_client_';

        parent::__construct();
    }


    public function fetchByClientId($client_id)
    {
        return DB::fetch_first('select * from %t where client_id=%s', array($this->_table, $client_id));
    }

}

?>