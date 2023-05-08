<?php

if (!defined('IN_DISCUZ')) {
    exit('Aecsse Denied');
}

class table_pushcat_subscribe extends discuz_table
{
    public function __construct()
    {

        $this->_table = 'pushcat_subscribe';
        $this->_pk = 'id';
        $this->_pre_cache_key = 'pushcat_subscribe_';

        parent::__construct();
    }

    public function fetchByUidTid($uid, $tid)
    {
        return DB::fetch_first('select * from %t where uid=%s and tid=%s', array($this->_table, $uid, $tid));
    }


}

?>