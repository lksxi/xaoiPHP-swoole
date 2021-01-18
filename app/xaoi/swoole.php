<?php
namespace xaoi;

class swoole{
	private $ws;
	private $route_conf;

	function __construct(){
		$this->route_conf = config('xaoi/route');
	}

	function onRequest(\swoole_http_request $req, \swoole_http_response $res){
		$res->header('Content-Type','text/html;charset=utf-8');
		$route = $this->get_route($req->server['path_info']);
		http('req',$req);
		http('res',$res);
		http('route',$route);
		$rcs = explode('\\',http('route.class'));
		$_ks = [];
		$_rcs = [];
		for($i = 0,$s = count($rcs);$i !== $s;++$i){
			$_k = $_ks;
			$_k[] = 'fns';
			$_rcs[] = implode('\\',$_k);
			$_ks[] = $rcs[$i];
		}
		ob_start();
		$str = $this->fns($_rcs,0,$s,[],function($args = [])use($route,$req,$res){
			try{
				$obj = $this->get_code($route['class'],$route['fn'],'http');
				if($obj){
					$next = function($args)use($obj,$req,$route){
						$is_call = true;
						$i = 0;
						$arr = [];
						foreach($obj[1] as $k => $param){
							if($param[0] === 1){
								$arr[] = $param[1];
							}elseif(isset($args[$k])){
								$arr[] = $args[$k];
								++$i;
							}elseif(isset($route['args'][$i])){
								$arr[] = $route['args'][$i];
								++$i;
							}elseif(isset($req->get[$k])){
								$arr[] = $req->get[$k];
								++$i;
							}elseif($param[0] === 2){
								$arr[] = $param[1];
								++$i;
							}else{
								$is_call = false;
								break;
							}
						}
						if($is_call){
							$r = call_user_func_array([$obj[0],$route['fn']],$arr);
							if(is_null($r)){
		
							}elseif(is_string($r)){
								return $r;
							}elseif(is_int($r)){
								return '['.$r.']';
							}elseif(is_array($r)){
								return json($r);
							}
						}else{
							return 'Parameter not defined';
						}
					};
					if(is_array($obj[2])){
						return $this->fns($obj[2],0,count($obj[2]),$args,$next);
					}else{
						return $next($args);
					}
				}else{
					$res->status(404);
					return '<html>'.
						'<head><title>404 Not Found</title></head>'.
						'<body>'.
						'<center><h1>404 Not Found</h1></center>'.
						'<hr><center>xaoi</center>'.
						'</body>'.
						'</html>';
				}
			}catch(\Exception $e){
				return $e->getMessage();
			}
		});
		$end = ob_get_contents();
		ob_end_clean();
		
		if(is_int($str)){
			$str = '['.$str.']';
		}elseif(is_array($str)){
			$str = json($str);
		}
		$res->end($str.$end);
	}

	function onHandshake(\swoole_http_request $req, \swoole_http_response $res){
		if($this->connect($req,$res)){
			// websocket握手连接算法验证
			$secWebSocketKey = $req->header['sec-websocket-key'];
			$patten = '#^[+/0-9A-Za-z]{21}[AQgw]==$#';
			if (0 === preg_match($patten, $secWebSocketKey) || 16 !== strlen(base64_decode($secWebSocketKey))) {
				$res->end();
				return false;
			}

			$key = base64_encode(sha1(
				$req->header['sec-websocket-key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11',
				true
			));

			$headers = [
				'Upgrade' => 'websocket',
				'Connection' => 'Upgrade',
				'Sec-WebSocket-Accept' => $key,
				'Sec-WebSocket-Version' => '13',
			];

			if (isset($req->header['sec-websocket-protocol'])) {
				$headers['Sec-WebSocket-Protocol'] = $req->header['sec-websocket-protocol'];
			}

			foreach ($headers as $key => $val) {
				$res->header($key, $val);
			}

			$res->status(101);
			$res->end();

			return true;
		}else{
			$res->end();
			return false;
		}
	}

	//关闭连接执行的闭包
	private $onclose_fn = [];
	private function connect(\swoole_http_request $req, \swoole_http_response $res){
		$route = $this->get_route($req->server['path_info']);
		http('fd',$req->fd);
		http('req',$req);
		http('res',$res);
		http('route',$route);
		try{
			$obj = $this->get_code($route['class'],$route['fn'],'websocket');
			if($obj){
				$args = [];
				$next = function($args)use($obj,$req,$route){
					$is_call = true;
					$i = 0;
					$arr = [];
					foreach($obj[1] as $k => $param){
						if($param[0] === 1){
							$arr[] = $param[1];
						}elseif(isset($args[$k])){
							$arr[] = $args[$k];
							++$i;
						}elseif(isset($route['args'][$i])){
							$arr[] = $route['args'][$i];
							++$i;
						}elseif(isset($req->get[$k])){
							$arr[] = $req->get[$k];
							++$i;
						}elseif($param[0] === 2){
							$arr[] = $param[1];
							++$i;
						}else{
							$is_call = false;
							break;
						}
					}
					if($is_call){
						$r = call_user_func_array([$obj[0],$route['fn']],$arr);
						if($r === true){
							return true;
						}elseif($r instanceof \Closure){
							$this->onclose_fn[$req->fd] = $r;
							return true;
						}
						return false;
					}else{
						return false;
					}
				};
				if(is_array($obj[2])){
					return $this->fns($obj[2],0,count($obj[2]),$args,$next);
				}else{
					return $next($args);
				}
			}else{
				return false;
			}
		}catch(\Exception $e){
			return false;
		}
	}

	function onMessage(\Swoole\WebSocket\Server $server, $frame){
		if(empty($frame->data))return;
		$data = json_decode($frame->data,true);
		if(!(!empty($data) && is_array($data) && !empty($data[0]) && is_string($data[0])))return;
		$pathinfo = array_shift($data);
		$route = $this->get_route($pathinfo);
		http('fd',$frame->fd);
		http('route',$route);
		try{
			$obj = $this->get_code($route['class'],$route['fn'],'websocket');
			if($obj){
				$args = [];
				$next = function($args)use($obj,$data,$route,$frame){
					$is_call = true;
					$i = 0;
					$arr = [];
					foreach($obj[1] as $k => $param){
						if($param[0] === 1){
							$arr[] = $param[1];
						}elseif(isset($args[$k])){
							$arr[] = $args[$k];
							++$i;
						}elseif(isset($route['args'][$i])){
							$arr[] = $route['args'][$i];
							++$i;
						}elseif(isset($data[$i])){
							$arr[] = $data[$i];
							++$i;
						}elseif($param[0] === 2){
							$arr[] = $param[1];
							++$i;
						}else{
							$is_call = false;
							break;
						}
					}
					if($is_call){
						$d = call_user_func_array([$obj[0],$route['fn']],$arr);
						if(is_string($d)){
							$this->ws->push($frame->fd,$d);
						}elseif(is_array($d)){
							$this->ws->push($frame->fd,json($d));
						}
					}
				};
				if(is_array($obj[2])){
					return $this->fns($obj[2],0,count($obj[2]),$args,$next);
				}else{
					return $next($args);
				}
			}else{
				return;
			}
		}catch(\Exception $e){
			$msg = $e->getMessage();
			$this->ws->push($frame->fd,$msg);
		}
	}

	function onClose(\Swoole\WebSocket\Server $server, $fd){
		if($server->isEstablished($fd)){
			if(!empty($this->onclose_fn[$fd])){
				try{
					http('fd',$fd);
					call_user_func($this->onclose_fn[$fd]);
					unset($this->onclose_fn[$fd]);
				}catch(\Exception $e){
					unset($this->onclose_fn[$fd]);
					return;
				}
			}
		}
	}

	function onTask($serv, ...$data){
		try{
			$data = $data[0]->data;

			list($class,$fn) = explode('::',array_shift($data));
			$class = str_replace('/','\\',url_path(str_replace(['\\','::'],['/','/'],$class)));
			if($class[0] !== '\\')$class = '\\'.$class;
			$m = new \ReflectionMethod($class, $fn);
			if ($m->isPublic()) {
				$d = call_user_func_array([app($class), $fn], $data);
			} else {
				$m->setAccessible(true);
				$d = $m->invokeArgs(app($class), $data);
			}
		}catch(\Exception $e){
			return;
		}
		return $d;
	}

	function onFinish($serv, $task_id, $data){
		if(is_array($data)){
			$class	= array_shift($data);
			$fn		= array_shift($data);
			$m = new \ReflectionMethod($class,$fn);
			if($m->isPublic()){
				$d = call_user_func_array([app($class),$fn],$data);
			}else{
				$m->setAccessible(true);
				$d = $m->invokeArgs(app($class),$data);
			}
			return $d;
		}
	}

	function onWorkerStart($serv, $worker_id){
		if($serv->taskworker){
			try{

				config('xaoi/bind.ontaskstart')($worker_id);

			}catch(\Exception $e){
				_echo($e->getMessage());
			}
		}else{
			try{

				config('xaoi/bind.onworkerstart')($worker_id);

			}catch(\Exception $e){
				_echo($e->getMessage());
			}
		}
	}

	function onStart($server){
		
	}

	function start($ip = '127.0.0.1',$port = 80,$mode = SWOOLE_BASE){
		\Swoole\Runtime::enableCoroutine(SWOOLE_HOOK_ALL | SWOOLE_HOOK_CURL);

		$this->ws = new \Swoole\WebSocket\Server($ip, $port,$mode);

		$set = config('xaoi/swoole');

		if(!APP_DEBUG){
			/*$set['user'] = 'www';
			$set['group'] = 'www';
			$set['daemonize'] = 1;*/
		}

		$this->ws->set($set);

		$this->ws->on('request',[$this,'onRequest']);

		$this->ws->on('handshake', [$this,'onHandshake']);

		$this->ws->on('message', [$this,'onMessage']);

		$this->ws->on('close', [$this,'onClose']);

		$this->ws->on('task', [$this,'onTask']);

		$this->ws->on('finish', [$this,'onFinish']);

		$this->ws->on('workerStart', [$this,'onWorkerStart']);

		config('xaoi/bind.onstart')();

		echo 'cpu size:'.swoole_cpu_num()."\n".'服务器启动 '.$ip.':'.$port."\n";
		$this->ws->start();
	}

	function send(){
		switch(func_num_args()){
			case 1:
				$fd = http('fd');
				$str = func_get_arg(0);
			break;
			case 2:
				$fd = func_get_arg(0);
				$str = func_get_arg(1);
			break;
		}
		if($this->ws->exist($fd))return $this->ws->push($fd,is_string($str)?$str:json($str));
	}

	function close($fd, bool $reset = false){
		return $this->ws->close($fd,$reset);
	}

	function reload(){
		\Swoole\Event::defer(function(){
			$this->ws->reload();
		});
	}

	function task(...$args){
		return call_user_func_array([$this->ws,'task'],$args);
	}

	function finish(...$args){
		return call_user_func_array([$this->ws,'finish'],$args);
	}

	// 对象缓存
	private $code_objs = [];
	function get_code($class,$fn,$is_route = false){
		if(empty($this->code_objs[$class])){
			if(class_exists($class)){
				switch($is_route){
					case 'http':
						if(is_subclass_of($class,'\xaoi\init')){
							$obj = app($class,[],true);
							$is_new = true;
						}elseif(is_subclass_of($class,'\xaoi\base')){
							$obj = app($class);
							$is_new = false;
						}else{
							return false;
						}
					break;
					case 'websocket':
						if(is_subclass_of($class,'\xaoi\websocket')){
							$obj = app($class);
							$is_new = false;
						}else{
							return false;
						}
					break;
					default:
						$obj = app($class);
						$is_new = false;
					break;
				}

				$fn_lis = get_class_methods($obj);
				$this->code_objs[$class] = [
					'is_new'	=> $is_new,
					'fn_lis'	=> [],
					'fns'		=> false
				];
				if(property_exists($obj,'fns')){
					$rc = new \ReflectionClass($obj);
					$objname = $rc->getProperty('fns');
					if(!$objname->isPublic())$objname->setAccessible(true);
					$this->code_objs[$class]['fns'] = $objname->getValue($obj);
				}
				foreach($fn_lis as $action){
					$method = new \ReflectionMethod($class,$action);
					$arr = [];
					$params = $method->getParameters();
					foreach ($params as $key => $param)
					{
						$c = $param->getClass();
						$is_def = $param->isDefaultValueAvailable();
						if($is_def){
							$arr[$param->getName()] = [2,$param->getDefaultValue()];
						}elseif($c){
							$arr[$param->getName()] = [1,app($c->getName())];
						}else{
							$arr[$param->getName()] = [0];
						}
					}
					$this->code_objs[$class]['fn_lis'][$action] = $arr;
				}
				if(isset($this->code_objs[$class]['fn_lis'][$fn])){
					return [$obj,$this->code_objs[$class]['fn_lis'][$fn],$this->code_objs[$class]['fns']];
				}else{
					return false;
				}
			}else
				return false;
		}else{
			if(isset($this->code_objs[$class]['fn_lis'][$fn])){
				return [$this->code_objs[$class]['is_new']?app($class,[],true):app($class),$this->code_objs[$class]['fn_lis'][$fn],$this->code_objs[$class]['fns']];
			}else{
				return false;
			}
		}
	}

	//路由解析
	function get_route($url){
		if($p = strripos($url,$this->route_conf['suffix']))$url = substr($url,0,$p);
		if($url[0] == '/')$url = substr($url,1);
		$vars = explode($this->route_conf['space_var'],$url,2);
		if(empty($vars[1])){
			$args = [];
		}else{
			$args = explode($this->route_conf['space'],$vars[1]);
		}
		$class = explode($this->route_conf['space'],$vars[0]);
		$def = $this->route_conf['default'];
		foreach($def as $k => $v){
			if(empty($class[$k]))$class[$k] = $def[$k];
		}

		$fn = array_pop($class);

		return ['class'=>implode('\\',$class),'fn'=>$fn,'args'=>$args];
	}

	//中间件处理
	//路径记录
	private $fns_path = [];
	private function fns($fns,$i,$s,$args,$next){
		if($i !== $s){
			$class = $fns[$i];
			if(is_string($class)){
				if(!isset($this->fns_path[$class])){
					$class = str_replace('/','\\',$class);
					if(class_exists($class) && is_subclass_of($class,'\xaoi\fns')){
						if(property_exists($class,'fns')){
							$obj = app($class);
							$rc = new \ReflectionClass($obj);
							$objname = $rc->getProperty('fns');
							if(!$objname->isPublic())$objname->setAccessible(true);
							$this->fns_path[$class] = $objname->getValue($obj);
						}else{
							$this->fns_path[$class] = true;
						}
					}else{
						$this->fns_path[$class] = false;
					}
				}
				if($this->fns_path[$class] === false){
					return $this->fns($fns,$i+1,$s,$args,$next);
				}elseif($this->fns_path[$class] === true){
					return call_user_func([app($class),'handle'],$args,function($args)use($fns,$i,$s,$next){
						return $this->fns($fns,$i+1,$s,$args,$next);
					});
				}elseif(is_array($this->fns_path[$class])){
					return $this->fns($this->fns_path[$class],0,count($this->fns_path[$class]),$args,function($args)use($fns,$i,$s,$next,$class){
						return call_user_func([app($class),'handle'],$args,function($args)use($fns,$i,$s,$next){
							return $this->fns($fns,$i+1,$s,$args,$next);
						});
					});
				}
			}elseif($class instanceof \Closure){
				return call_user_func($class,$args,function($args)use($fns,$i,$s,$next){
					return $this->fns($fns,$i+1,$s,$args,$next);
				});
			}
		}else{
			return call_user_func($next,$args);
		}
	}
}