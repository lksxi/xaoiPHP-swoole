<?php
namespace xaoi;

class session{
	private $session_id = null;
	private $sessions = null;
	private $session_prefix = '';
	private $sid;

	function __construct($sid = null,$fn = []){
		if(!empty($sid)){
			$this->init($sid,$fn);
		}
	}

	function init($sid,$fn = []){
		$this->session_id = $sid;
		$this->sid = $this->session_prefix.$this->session_id;

		if(redis($this->sid)->hLen() > 0){
			$this->sessions = [];
			$keys = redis($this->sid)->hKeys();
			foreach($keys as $k){
				$this->sessions[$k] = redis($this->sid)->hGet($k);
			}
		}else{
			$this->sessions = is_array($fn)?$fn:call_user_func($fn);
			$r = redis($this->sid)->hMset($this->sessions);
		}
		redis($this->sid)->expire(config('xaoi/session.expire'));
	}

	function prefix(){
		switch(func_num_args()){
			case 0:
				return $this->session_prefix;
			break;
			case 1:
				$this->session_prefix = func_get_arg(0) . '_';
			break;
		}
	}

	function del($sid){
		$r = redis($this->sid)->del();
		return $r;
	}

	function session_id(){
		switch(func_num_args()){
			case 0:
				return $this->session_id;
			break;
			case 1:
				redis($this->sid)->del();
				$this->session_id = func_get_arg(0);
				$this->sessions = [];
				$this->sid = $this->session_prefix.$this->session_id;
			break;
		}
	}

	function session(){
		if(is_null($this->sessions)){
			$session_id = cookie('XAOISESSID');
			$this->sid = $this->session_prefix.$session_id;
			if(!empty($session_id)){
				$this->sessions = [];
				if(redis($this->sid)->hLen() > 0){
					$keys = redis($this->sid)->hKeys();
					foreach($keys as $k){
						$this->sessions[$k] = redis($this->sid)->hGet($k);
					}
				}
			}else{
				$session_id = session_create_id();
				$this->sessions = [];
				http('res')->cookie('XAOISESSID',$session_id,0,'/','',false,true,'');
			}
			redis($this->sid)->expire(config('xaoi/session.expire'));
			$this->session_id = $session_id;
		}
		switch(func_num_args()){
			case 0:
				return $this->sessions;
			break;
			case 1:
				$k = func_get_arg(0);
				if(is_null($k)){
					$this->sessions = null;
					$r = redis($this->sid)->del();
					return $r;
				}elseif(is_array($k)){
					$this->sessions = $k;
					redis($this->sid)->del();
					return redis($this->sid)->hMset($this->sessions);
				}elseif(!empty($k)){
					$k = explode('.',$k);
					$p = &$this->sessions;
					for($i=0,$l=count($k);$i!=$l;++$i){
						if(!is_array($p))return;
						$p = &$p[$k[$i]];
					}
					return $p;
				}
			break;
			case 2:
				$k = func_get_arg(0);
				$v = func_get_arg(1);
				if(!empty($k)){
					$k = explode('.',$k);
					$p = &$this->sessions;
					for($i=0,$l=count($k);$i!=$l;++$i){
						if(!is_array($p))$p=array();
						$p = &$p[$k[$i]];
					}
					$p = $v;
					$r = is_null($this->sessions[$k[0]])?redis($this->sid)->hDel($k[0]):redis($this->sid)->hSet($k[0],$this->sessions[$k[0]]);
					return $r;
				}
			break;
		}
	}
}