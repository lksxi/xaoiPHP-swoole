<?php
namespace xaoi;

class mysql{
	private $config;
	private $chans = [];
	private $keeps = [];
	private $chan_db = [];
	private $chan_tab = [];
	private $keep_db = [];
	private $keep_tab = [];

	//计数器
	private $slave_sum = [];
	private $slave_int = [];

	//是否连接池
	private $is_pool = [];
	private $conns = [];

	function __construct(){
		$config = env('mysql');
		$this->config = $config;

		foreach($config as $k => $v){
			$this->is_pool[$k] = $v['is_pool'];
			if($v['is_pool']){
				$this->chans[$k] = new mysql_pool($v,swoole_cpu_num() * $v['chan'][0] + $v['chan'][1],$v['pool_timeout']);
				$this->keeps[$k] = new mysql_pool($v,swoole_cpu_num() * $v['keep'][0] + $v['keep'][1],$v['pool_timeout']);
			}else{
				$this->conns[$k] = new mysql_conn($k,$v);
			}
			
			if(!empty($v['slave'])){
				$this->slave_sum[$k] = count($v['slave']);
				$this->slave_int[$k] = new \Swoole\Atomic();
				$slave = $v['slave'];
				unset($v['slave']);
				foreach($slave as $k2 => $v2){
					$v2 += $v;
					if($v['is_pool']){
						$this->chans[$k.':'.$k2] = new mysql_pool($v2,swoole_cpu_num() * $v2['chan'][0] + $v2['chan'][1],$v2['pool_timeout']);
						$this->keeps[$k.':'.$k2] = new mysql_pool($v2,swoole_cpu_num() * $v2['keep'][0] + $v2['keep'][1],$v2['pool_timeout']);
					}else{
						$this->conns[$k.':'.$k2] = new mysql_conn($k.':'.$k2,$v2);
					}
				}
			}else{
				$this->slave_sum[$k] = 0;
			}
		}
	}

	function is_debug($k){
		return $this->config[$k]['debug'];
	}

	function chan($k,$is_get,$fn){
		if($this->slave_sum[$k] === 0 || !$is_get){
			//主库
			$_k = $k;
		}else{
			//从库
			$i = $this->slave_int[$k]->add()-1;
			$this->slave_int[$k]->cmpset($this->slave_sum[$k],0);
			$_k = $k.':'.$i;
		}
		if($this->is_pool[$k]){
			$db = $this->chans[$_k]->get();

			if($db === false){
				_exit(config('xaoi/state_code.mysql.pool_timeout'));
			}
				
			try{
				$r = call_user_func($fn,$db);
				$this->chans[$_k]->put($db);
			}catch(\Exception $e){
				$this->chans[$_k]->put($db);
				throw $e;
			}
		}else{
			$db = $this->conns[$_k]->get();
			$r = call_user_func($fn,$db);
		}
		if($r === false)_exit(config('xaoi/state_code.mysql.error'));
		return $r;
	}

	function keep($k,$d = null){
		switch(func_num_args()){
			case 1:
				if($this->is_pool[$k]){
					$db = $this->keeps[$k]->get();
					if($db === false){
						_exit(config('xaoi/state_code.mysql.pool_timeout'));
					}
					return $db;
				}else{
					return $this->conns[$k]->get();
				}
			break;
			case 2:
				if($this->is_pool[$k])$this->keeps[$k]->put($d);
			break;
		}
	}

	function db(){
		switch(func_num_args()){
			case 0:
				$dbname = 'default';
				$tab = null;
				$is_keep = false;
			break;
			case 1:
				$dbname = 'default';
				$tab = func_get_arg(0);
				if($tab === true || (!is_string($tab) && $tab instanceof \Closure)){
					$is_keep = true;
				}else{
					$is_keep = false;
				}
			break;
			case 2:
				$dbname = func_get_arg(0);
				$tab = func_get_arg(1);
				if($tab === true || (!is_string($tab) && $tab instanceof \Closure)){
					$is_keep = true;
				}else{
					$is_keep = false;
				}
			break;
		}
		if($is_keep){
			if(empty($this->keep_db[$dbname])){
				$this->keep_db[$dbname] = new mysql_keep($this,$dbname);
				$this->keep_tab[$dbname] = [];
			}
			if(!is_string($tab) && $tab instanceof \Closure){
				$this->keep_db[$dbname]->begin();
				try{
					$ref = new \ReflectionFunction($tab);
					$par = $ref->getParameters();
					array_shift($par);
					$args = [$this->keep_db[$dbname]];
					foreach($par as $n){
						$tabname = $n->getName();
						if(empty($this->keep_tab[$dbname][$tabname])){
							$this->keep_tab[$dbname][$tabname] = new mysql_tab($this->keep_db[$dbname],$tabname);
						}
						$args[] = $this->keep_tab[$dbname][$tabname];
					}
					$r = call_user_func_array($tab,$args);
					if(is_null($r)){
						$this->keep_db[$dbname]->commit();
					}else{
						$this->keep_db[$dbname]->rollback();
					}
					return $r;
				}catch(\PDOException $e){
					$this->keep_db[$dbname]->rollback();
					_exit(config('xaoi/state_code.mysql.error'));
				}catch(\Exception $e){
					$this->keep_db[$dbname]->rollback();
					throw $e;
				}
			}else{
				return $this->keep_db[$dbname];
			}
		}else{
			if(empty($this->chan_db[$dbname])){
				$this->chan_db[$dbname] = new mysql_db($this,$dbname);
				$this->chan_tab[$dbname] = [];
			}
			if(!empty($tab) && is_string($tab)){
				if(empty($this->chan_tab[$dbname][$tab])){
					$this->chan_tab[$dbname][$tab] = new mysql_tab($this->chan_db[$dbname],$tab);
				}
				return $this->chan_tab[$dbname][$tab];
			}else{
				return $this->chan_db[$dbname];
			}
		}
	}

	function log($sql,$bind = null){
		$f = getcwd() . '/log/mysql/sql/'.date('y_n/j').'.txt';
		if(!is_dir(dirname($f)))mkdir(dirname($f),0755,true);
		file_put_contents($f,'['.date('Y-m-d H:i:s').'] '.
			(empty($sql)?'':'sql: '.$sql).
			(empty($bind)?'':"\n".'bind: '.print_r($bind,true))."\n",FILE_APPEND);
	}

	function error($conn,$sql = null,$bind = null){
		if(APP_DEBUG){
			$bug = debug_backtrace();
			$_bug = [];
			array_splice($bug,0,3);
			array_splice($bug,count($bug)-1,1);
			foreach($bug as $v){
				$item = [];
				$item['function'] = $v['function'];
				if(!empty($v['line']))$item['line'] = $v['line'];
				if(!empty($v['file']))$item['file'] = $v['file'];
				if(!empty($v['class']))$item['class'] = $v['class'];
				$_bug[] = $item;
			}
			$f = getcwd() . '/log/mysql/error/'.date('y_n/j').'.txt';
			if(!is_dir(dirname($f)))mkdir(dirname($f),0755,true);
			file_put_contents($f,'['.date('Y-m-d H:i:s').'] '.
				(empty($sql)?'':'sql: '.$sql).
				(empty($conn->errorInfo()[2])?'':"\n".'msg: '.$conn->errorInfo()[2]).
				(empty($bind)?'':"\n".'bind: '.print_r($bind,true))."\n",FILE_APPEND);
			_echo([
				'sql'=>$sql,
				'bind'=>$bind,
				'code'=>$conn->errorCode(),
				'info'=>$conn->errorInfo(),
				'bug'=>$_bug,
			]);
		}
	}
}

//带连接池-设置超时时间
class mysql_pool extends \Swoole\ConnectionPool
{
	private $pool_timeout;

    /** @var int */
    protected $size = 64;

    /** @var PDOConfig */
    protected $config;

    public function __construct($config, int $size = self::DEFAULT_SIZE,int $pool_timeout = 30)
    {
        $this->pool_timeout = $pool_timeout;

        $this->config = $config;
        parent::__construct(function () {
			try{
				return new \PDO('mysql:host='.$this->config['host'].';port='.$this->config['port'].';dbname='.$this->config['database'].
					';charset='.$this->config['charset'],$this->config['user'],$this->config['password'],[
						\PDO::ATTR_ERRMODE				=> \PDO::ERRMODE_EXCEPTION,
						\PDO::ATTR_EMULATE_PREPARES		=> false,
						\PDO::ATTR_STRINGIFY_FETCHES	=> false
					]);
			}catch(\PDOException $e) {
				_exit(config('xaoi/state_code.mysql.connect'));
			}
        }, $size, \Swoole\Database\PDOProxy::class);
    }

    public function get()
    {
        if ($this->pool === null) {
            throw new \RuntimeException('Pool has been closed');
        }
        if ($this->pool->isEmpty() && $this->num < $this->size) {
            $this->make();
        }
        return $this->pool->pop($this->pool_timeout);
    }
}

//不带连接池
class mysql_conn{
	private $name;
	private $conf;

	function __construct($name,$conf){
		$this->name = $name;
		$this->conf = $conf;
	}

	function get(){
		$db = http('mysql:'.$this->name);

		http('mysql2:'.$this->name,3333);
		if(empty($db)){
			try{
				$db = new \PDO('mysql:host='.$this->conf['host'].';port='.$this->conf['port'].';dbname='.$this->conf['database'].
					';charset='.$this->conf['charset'],$this->conf['user'],$this->conf['password']);
				$db->setAttribute(\PDO::ATTR_ERRMODE,\PDO::ERRMODE_EXCEPTION);
				$db->setAttribute(\PDO::ATTR_EMULATE_PREPARES,false);
				$db->setAttribute(\PDO::ATTR_STRINGIFY_FETCHES,false);
			}catch(\PDOException $e) {
				_echo($e);
				_exit(config('xaoi/state_code.mysql.connect'));
			}
			http('mysql:'.$this->name,$db);
		}
		return $db;
	}
}

//数据库对象
class mysql_db{
	private $dbname;
	private $db;
	private $is_debug;

	function __construct($db,$dbname){
		$this->db		= $db;
		$this->dbname	= $dbname;
		$this->is_debug = $db->is_debug($dbname);
	}

	function query($sql,$type = 'get'){
		if($this->is_debug)$this->db->log($sql);
		return $this->db->chan($this->dbname,$type === 'get',function($conn)use($type,$sql){
			try{
				$ret = $conn->query($sql);
			}catch(\PDOException $e){
				$this->db->error($conn,$sql);
				return false;
			}
			switch($type){
				case 'get':
					$ret = $ret->fetchAll(\PDO::FETCH_ASSOC);
				break;
				case 'add':
					$ret = $conn->lastInsertId();
				break;
				case 'set':
				case 'del':
					$ret = $ret->rowCount();
				break;
			}
			return $ret;
		});
	}

	function _query($sql,$bind,$type = 'get'){
		if($this->is_debug)$this->db->log($sql,$bind);
		return $this->db->chan($this->dbname,$type === 'get',function($conn)use($type,$sql,$bind){
			try{
				$stmt = $conn->prepare($sql);
			}catch(\PDOException $e){
				$this->db->error($conn,$sql,$bind);
				return false;
			}
			try{
				$stmt->execute($bind);
			}catch(\PDOException $e){
				$this->db->error($stmt,$sql,$bind);
				return false;
			}
			switch($type){
				case 'get':
					$ret = $stmt->fetchAll(\PDO::FETCH_ASSOC);
				break;
				case 'add':
					$ret = $conn->lastInsertId();
				break;
				case 'set':
				case 'del':
					$ret = $stmt->rowCount();
				break;
			}
			return $ret;
		});
	}
}

//数据表对象
class mysql_tab{
	protected $db;
	private $tab;

	function __construct($db,$tab){
		$this->db = $db;
		$this->tab = $tab;
	}

	private function is_limit($v){
		if(is_numeric($v))return true;
		if(is_array($v) && count($v) === 2 && isset($v[0]) && isset($v[1]) && is_numeric($v[0]) && is_numeric($v[1]))return true;
	}

	function add($d){
		$sql = ['insert','into','`'.$this->tab.'`','field'=>'','values','value'=>''];
		$field = [];
		$str = [];
		$bind = [];
		foreach($d as $k => $v){
			$field[] = $k;
			$str[] = '?';
			$bind[] = $v;
		}
		$sql['field'] = '(`'.implode('`,`',$field).'`)';
		$sql['value'] = '('.implode(',',$str).')';

		return $this->db->_query(implode(' ',$sql),$bind,'add');
	}

	function _add(){
		$args = func_get_args();
		$chan = new \chan(1);
		go(function()use($chan,$args){
			$chan->push(call_user_func_array([$this,'add'],$args));
		});
		return function()use($chan){
			return $chan->pop();
		};
	}

	function get(){
		$where = [];
		$field = '';
		$group = '';
		$order = '';
		$limit = '';
		$d = func_get_args();
		switch(count($d)){
			case 1:
				if($this->is_limit($d[0])){
					$limit = $d[0];
				}else{
					$where = $d[0];
				}
			break;
			case 2:
				if($this->is_limit($d[1])){
					list($where,$limit) = $d;
				}else{
					list($where,$field) = $d;
				}
			break;
			case 3:
				if($this->is_limit($d[2])){
					list($where,$field,$limit) = $d;
				}else{
					list($where,$field,$order) = $d;
				}
			break;
			case 4:
				if($this->is_limit($d[3])){
					list($where,$field,$order,$limit) = $d;
				}else{
					list($where,$field,$group,$order) = $d;
				}
			break;
			case 5:
				list($where,$field,$group,$order,$limit) = $d;
			break;
		}

		$sql = ['select','field'=>'*','from',$this->tab,'where'=>'','group'=>'','order'=>'','limit'=>''];
		$sql_bind = [];
		if(!empty($field)){
			$field = explode(',',$field);
			$l = [];
			foreach($field as $v){
				if(stripos($this->tab,'as') || stripos($v,'as')){
					$l[] = $v;
				}else{
					$l[] = '`'.$v.'`';
				}
			}
			$sql['field'] = implode(',',$l);
		}
		if(!empty($where)){
			$str = [];
			foreach($where as $k => $v){
				$s = substr_count($k,'?');
				if($s === 0){
					if(is_array($v)){
						if(empty($v))return [];
						$ins = [];
						foreach($v as $v2){
							$ins[] = '?';
							$sql_bind[] = $v2;
						}
						$str[] = $k.' in('.implode(',',$ins).')';
					}else{
						$str[] = $k.' = ?';
						$sql_bind[] = $v;
					}
				}elseif($s === 1){
					$str[] = $k;
					$sql_bind[] = is_array($v)?$v[0]:$v;
				}else{
					$str[] = $k;
					foreach($v as $v2){
						$sql_bind[] = $v2;
					}
				}
			}
			$sql['where'] = 'where '.implode(' and ',$str);
		}
		if(!empty($group)){
			if(is_string($group))
				$sql['group'] = 'group by ' . $group;
			else if(is_array($group)){
				$sql['group'] = 'group by '.implode(',',$group);
			}
		}
		if(!empty($order)){
			if(is_string($order))
				$sql['order'] = 'order by ' . $order;
			else if(is_array($order)){
				$str = [];
				foreach($order as $k => $v){
					$str[] = $k.' '.($v?'desc':'asc');
				}
				$sql['order'] = 'order by '.implode(',',$str);
			}
		}
		if(!empty($limit)){
			if(is_numeric($limit)){
				$start	= $limit;
			}else{
				$start	= (int)$limit[0];
				$size	= (int)$limit[1];
			}
			$sql['limit'] = 'limit ' . $start . (!empty($size)? ','.$size: '');
		}
		$ret = $this->db->_query(implode(' ',$sql),$sql_bind,'get');
		return $limit === 1?(empty($ret)?[]:$ret[0]):$ret;
	}

	function _get(){
		$args = func_get_args();
		$chan = new \chan(1);
		go(function()use($chan,$args){
			$chan->push(call_user_func_array([$this,'get'],$args));
		});
		return function()use($chan){
			return $chan->pop();
		};
	}

	function set(){
		$where = [];
		$set = '';
		$d = func_get_args();
		switch(count($d)){
			case 1:
				$set = $d[0];
			break;
			case 2:
				$where = $d[0];
				$set = $d[1];
			break;
		}

		$sql = ['update','`'.$this->tab.'`','set','set'=>'','where'=>''];
		$sql_bind = [];
		if(is_array($set)){
			$str = [];
			foreach($set as $k => $v){
				if(is_array($v)){
					$str[] = $k;
					foreach($v as $v2)$sql_bind[] = $v2;
				}else{
					$str[] = '`'.$k.'`=?';
					$sql_bind[] = $v;
				}
			}
			$sql['set'] = implode(',',$str);
		}else{
			$sql['set'] = $set;
		}
		if(!empty($where)){
			$str = [];
			foreach($where as $k => $v){
				$s = substr_count($k,'?');
				if($s === 0){
					if(is_array($v)){
						$ins = [];
						foreach($v as $v2){
							$ins[] = '?';
							$sql_bind[] = $v2;
						}
						$str[] = $k.' in('.implode(',',$ins).')';
					}else{
						$str[] = $k.' = ?';
						$sql_bind[] = $v;
					}
				}elseif($s === 1){
					$str[] = $k;
					$sql_bind[] = is_array($v)?$v[0]:$v;
				}else{
					$str[] = $k;
					foreach($v as $v2){
						$sql_bind[] = $v2;
					}
				}
			}
			$sql['where'] = 'where '.implode(' and ',$str);
		}
		return $this->db->_query(implode(' ',$sql),$sql_bind,'set');
	}

	function _set(){
		$args = func_get_args();
		$chan = new \chan(1);
		go(function()use($chan,$args){
			$chan->push(call_user_func_array([$this,'set'],$args));
		});
		return function()use($chan){
			return $chan->pop();
		};
	}

	function del($d){
		$sql = ['delete','from','`'.$this->tab.'`','where'=>''];
		$sql_bind = [];
		$str = [];
		foreach($d as $k => $v){
			$s = substr_count($k,'?');
			if($s === 0){
				if(is_array($v)){
					$ins = [];
					foreach($v as $v2){
						$ins[] = '?';
						$sql_bind[] = $v2;
					}
					$str[] = $k.' in('.implode(',',$ins).')';
				}else{
					$str[] = $k.' = ?';
					$sql_bind[] = $v;
				}
			}elseif($s === 1){
				$str[] = $k;
				$sql_bind[] = is_array($v)?$v[0]:$v;
			}else{
				$str[] = $k;
				foreach($v as $v2){
					$sql_bind[] = $v2;
				}
			}
		}
		$sql['where'] = 'where '.implode(' and ',$str);
		return $this->db->_query(implode(' ',$sql),$sql_bind,'del');
	}

	function _del(){
		$args = func_get_args();
		$chan = new \chan(1);
		go(function()use($chan,$args){
			$chan->push(call_user_func_array([$this,'del'],$args));
		});
		return function()use($chan){
			return $chan->pop();
		};
	}
}

//长连接-数据库对象
class mysql_keep{
	private $conn;
	private $dbname;
	private $is_begin = false;
	private $db;
	private $is_debug;

	function __construct($db,$dbname){
		$this->db		= $db;
		$this->dbname	= $dbname;
		$this->is_debug = $db->is_debug($dbname);
	}

	private function is_limit($v){
		if(is_numeric($v))return true;
		if(is_array($v) && count($v) === 2 && isset($v[0]) && isset($v[1]) && is_numeric($v[0]) && is_numeric($v[1]))return true;
	}

	function begin(){
		if($this->is_begin === true)return;
		$this->conn = $this->db->keep($this->dbname);
		$e = $this->conn->beginTransaction();
		$this->is_begin = true;
		return $e;
	}

	function commit(){
		if($this->is_begin === false)return;
		$e = $this->conn->commit();
		$this->is_begin = false;
		$this->db->keep($this->dbname,$this->conn);
		return $e;
	}

	function rollback(){
		if($this->is_begin === false)return;
		$e = $this->conn->rollback();
		$this->is_begin = false;
		$this->db->keep($this->dbname,$this->conn);
		return $e;
	}

	function query($sql,$type = 'get'){
		if($this->is_debug)$this->db->log($sql);
		try{
			$ret = $this->conn->query($sql);
		}catch(\PDOException $e){
			$this->db->error($this->conn,$sql);
			throw $e;
		}
		switch($type){
			case 'get':
				$ret = $ret->fetchAll(\PDO::FETCH_ASSOC);
			break;
			case 'add':
				$ret = $this->conn->lastInsertId();
			break;
			case 'set':
			case 'del':
				$ret = $ret->rowCount();
			break;
		}
		return $ret;
	}

	function prepare($sql){
		try{
			$stmt = $this->conn->prepare($sql);
		}catch(\PDOException $e){
			$this->db->error($this->conn,$sql);
			throw $e;
		}
		return $stmt;
	}

	function _query($sql,$bind,$type = 'get'){
		if($this->is_debug)$this->db->log($sql,$bind);
		try{
			$stmt = $this->conn->prepare($sql);
		}catch(\PDOException $e){
			$this->db->error($this->conn,$sql,$bind);
			throw $e;
		}
		try{
			$stmt->execute($bind);
		}catch(\PDOException $e){
			$this->db->error($stmt,$sql,$bind);
			throw $e;
		}
		switch($type){
			case 'get':
				$ret = $stmt->fetchAll(\PDO::FETCH_ASSOC);
			break;
			case 'add':
				$ret = $this->conn->lastInsertId();
			break;
			case 'set':
			case 'del':
				$ret = $stmt->rowCount();
			break;
		}
		return $ret;
	}

	function add($tab,$d){
		$sql = ['insert','into','`'.$tab.'`','field'=>'','values','value'=>''];
		$field = [];
		$str = [];
		$bind = [];
		foreach($d as $k => $v){
			$field[] = $k;
			$str[] = '?';
			$bind[] = $v;
		}
		$sql['field'] = '(`'.implode('`,`',$field).'`)';
		$sql['value'] = '('.implode(',',$str).')';
		return $this->_query(implode(' ',$sql),$bind,'add');
	}

	function get($tab){
		$where = [];
		$field = '';
		$group = '';
		$order = '';
		$limit = '';
		$d = func_get_args();
		array_shift($d);
		switch(count($d)){
			case 1:
				if($this->is_limit($d[0])){
					$limit = $d[0];
				}else{
					$where = $d[0];
				}
			break;
			case 2:
				if($this->is_limit($d[1])){
					list($where,$limit) = $d;
				}else{
					list($where,$field) = $d;
				}
			break;
			case 3:
				if($this->is_limit($d[2])){
					list($where,$field,$limit) = $d;
				}else{
					list($where,$field,$order) = $d;
				}
			break;
			case 4:
				if($this->is_limit($d[3])){
					list($where,$field,$order,$limit) = $d;
				}else{
					list($where,$field,$group,$order) = $d;
				}
			break;
			case 5:
				list($where,$field,$group,$order,$limit) = $d;
			break;
		}

		$sql = ['select','field'=>'*','from',$tab,'where'=>'','group'=>'','order'=>'','limit'=>''];
		$sql_bind = [];
		if(!empty($field)){
			$field = explode(',',$field);
			$l = [];
			foreach($field as $v){
				if(stripos($tab,'as') || stripos($v,'as')){
					$l[] = $v;
				}else{
					$l[] = '`'.$v.'`';
				}
			}
			$sql['field'] = implode(',',$l);
		}
		if(!empty($where)){
			$str = [];
			foreach($where as $k => $v){
				$s = substr_count($k,'?');
				if($s === 0){
					if(is_array($v)){
						$ins = [];
						foreach($v as $v2){
							$ins[] = '?';
							$sql_bind[] = $v2;
						}
						$str[] = $k.' in('.implode(',',$ins).')';
					}else{
						$str[] = $k.' = ?';
						$sql_bind[] = $v;
					}
				}elseif($s === 1){
					$str[] = $k;
					$sql_bind[] = is_array($v)?$v[0]:$v;
				}else{
					$str[] = $k;
					foreach($v as $v2){
						$sql_bind[] = $v2;
					}
				}
			}
			$sql['where'] = 'where '.implode(' and ',$str);
		}
		if(!empty($group)){
			if(is_string($group))
				$sql['group'] = 'group by ' . $group;
			else if(is_array($group)){
				$sql['group'] = 'group by '.implode(',',$group);
			}
		}
		if(!empty($order)){
			if(is_string($order))
				$sql['order'] = 'order by ' . $order;
			else if(is_array($order)){
				$str = [];
				foreach($order as $k => $v){
					$str[] = $k.' '.($v?'desc':'asc');
				}
				$sql['order'] = 'order by '.implode(',',$str);
			}
		}
		if(!empty($limit)){
			if(is_numeric($limit)){
				$start	= $limit;
			}else{
				$start	= (int)$limit[0];
				$size	= (int)$limit[1];
			}
			$sql['limit'] = 'limit ' . $start . (!empty($size)? ','.$size: '');
		}
		$ret = $this->_query(implode(' ',$sql),$sql_bind,'get');
		return $limit === 1?(empty($ret)?[]:$ret[0]):$ret;
	}

	function set($tab){
		$where = [];
		$set = '';
		$d = func_get_args();
		array_shift($d);
		switch(count($d)){
			case 1:
				$set = $d[0];
			break;
			case 2:
				$where = $d[0];
				$set = $d[1];
			break;
		}

		$sql = ['update','`'.$tab.'`','set','set'=>'','where'=>''];
		$sql_bind = [];
		if(is_array($set)){
			$str = [];
			foreach($set as $k => $v){
				if(is_array($v)){
					$str[] = $k;
					foreach($v as $v2)$sql_bind[] = $v2;
				}else{
					$str[] = '`'.$k.'`=?';
					$sql_bind[] = $v;
				}
			}
			$sql['set'] = implode(',',$str);
		}else{
			$sql['set'] = $set;
		}
		if(!empty($where)){
			$str = [];
			foreach($where as $k => $v){
				$s = substr_count($k,'?');
				if($s === 0){
					if(is_array($v)){
						$ins = [];
						foreach($v as $v2){
							$ins[] = '?';
							$sql_bind[] = $v2;
						}
						$str[] = $k.' in('.implode(',',$ins).')';
					}else{
						$str[] = $k.' = ?';
						$sql_bind[] = $v;
					}
				}elseif($s === 1){
					$str[] = $k;
					$sql_bind[] = is_array($v)?$v[0]:$v;
				}else{
					$str[] = $k;
					foreach($v as $v2){
						$sql_bind[] = $v2;
					}
				}
			}
			$sql['where'] = 'where '.implode(' and ',$str);
		}
		return $this->_query(implode(' ',$sql),$sql_bind,'set');
	}

	function del($tab,$d){
		$sql = ['delete','from','`'.$tab.'`','where'=>''];
		$sql_bind = [];
		$str = [];
		foreach($d as $k => $v){
			$s = substr_count($k,'?');
			if($s === 0){
				if(is_array($v)){
					$ins = [];
					foreach($v as $v2){
						$ins[] = '?';
						$sql_bind[] = $v2;
					}
					$str[] = $k.' in('.implode(',',$ins).')';
				}else{
					$str[] = $k.' = ?';
					$sql_bind[] = $v;
				}
			}elseif($s === 1){
				$str[] = $k;
				$sql_bind[] = is_array($v)?$v[0]:$v;
			}else{
				$str[] = $k;
				foreach($v as $v2){
					$sql_bind[] = $v2;
				}
			}
		}
		$sql['where'] = 'where '.implode(' and ',$str);
		return $this->_query(implode(' ',$sql),$sql_bind,'del');
	}
}