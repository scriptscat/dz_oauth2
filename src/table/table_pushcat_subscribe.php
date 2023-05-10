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

    public function updateStatus($id, $status)
    {
        return DB::update($this->_table, array('status' => $status), array('id' => $id));
    }

    public function create($uid, $tid)
    {
        return C::t('#codfrm_oauth2#pushcat_subscribe')->insert(array(
            'uid' => $uid,
            'tid' => $tid,
            'status' => 1,
            'createtime' => time()
        ));
    }

    public function fetchByTid($tid)
    {
        return DB::fetch_all('select * from %t where tid=%s and status=1', array($this->_table, $tid));
    }

}

?>