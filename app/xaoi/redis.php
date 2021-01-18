<?php
namespace xaoi;

class redis{
	private $chans = [];

	function __construct(){
		$config = env('redis');

		foreach($config as $k => $v){
			$conf = new \Swoole\Database\RedisConfig;
			$conf->withHost($v['host']);
			$conf->withPort($v['port']);
			$conf->withAuth($v['pwd']);
			$conf->withDbIndex($v['database']);
			$conf->withTimeout(1);
			
			$this->chans[$k] = new \Swoole\Database\RedisPool($conf,swoole_cpu_num() * $v['chan'][0] + $v['chan'][1]);
		}
	}

	function chan($k,$d = null){
		if(empty($d)){
			return $this->chans[$k]->get();
		}else{
			$this->chans[$k]->put($d);
		}
	}
}

class redis_db{
	private $db_name;
	private $tab_name;
	private $obj;
	function __construct($db_name,$tab_name){
		$this->db_name = $db_name;
		$this->tab_name = $tab_name;
		$this->obj = app('\xaoi\redis');
	}

	function expire($d){
		$redis = $this->obj->chan($this->db_name);
		$r = $redis->expire($this->tab_name,$d);
		$this->obj->chan($this->db_name,$redis);
		return $r;
	}

	function exists(){
		$redis = $this->obj->chan($this->db_name);
		$r = $redis->exists($this->tab_name);
		$this->obj->chan($this->db_name,$redis);
		return $r;
	}

	function get(){
		$redis = $this->obj->chan($this->db_name);
		$r = unserialize($redis->get($this->tab_name));
		$this->obj->chan($this->db_name,$redis);
		return $r;
	}

	function set($d){
		$redis = $this->obj->chan($this->db_name);
		$r = $redis->set($this->tab_name,serialize($d));
		$this->obj->chan($this->db_name,$redis);
		return $r;
	}

	function del(){
		$redis = $this->obj->chan($this->db_name);
		$r = $redis->del($this->tab_name);
		$this->obj->chan($this->db_name,$redis);
		return $r;
	}

	function hExists($k){
		$redis = $this->obj->chan($this->db_name);
		$r = $redis->hExists($this->tab_name,$k);
		$this->obj->chan($this->db_name,$redis);
		return $r;
	}

	function hLen(){
		$redis = $this->obj->chan($this->db_name);
		$r = $redis->hLen($this->tab_name);
		$this->obj->chan($this->db_name,$redis);
		return $r;
	}

	function hKeys(){
		$redis = $this->obj->chan($this->db_name);
		$r = $redis->hKeys($this->tab_name);
		$this->obj->chan($this->db_name,$redis);
		return $r;
	}

	function hGet($k){
		$redis = $this->obj->chan($this->db_name);
		$r = unserialize($redis->hGet($this->tab_name,$k));
		$this->obj->chan($this->db_name,$redis);
		return $r;
	}

	//获取全部键
	function hGetAll(){
		$redis = $this->obj->chan($this->db_name);
		$r = $redis->hGetAll($this->tab_name);
		foreach($r as &$v)$v = unserialize($v);unset($v);
		$this->obj->chan($this->db_name,$redis);
		return $r;
	}

	function hSet($k,$d){
		$redis = $this->obj->chan($this->db_name);
		$r = $redis->hSet($this->tab_name,$k,serialize($d));
		$this->obj->chan($this->db_name,$redis);
		return $r;
	}

	function hMset($d){
		$redis = $this->obj->chan($this->db_name);
		foreach($d as &$v)$v = serialize($v);unset($v);
		$r = $redis->hMset($this->tab_name,$d);
		$this->obj->chan($this->db_name,$redis);
		return $r;
	}

	function hDel($k){
		$redis = $this->obj->chan($this->db_name);
		$r = $redis->hDel($this->tab_name,$k);
		$this->obj->chan($this->db_name,$redis);
		return $r;
	}
}