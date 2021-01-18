<?php
//declare (strict_types = 1);


//获取配置 路径/文件.变量名
function config($name){
	static $_ps = [];
	static $_conf = [];

	switch(func_num_args()){
		case 1:
			if(!empty($_ps[$name]))return $_ps[$name];

			if(strpos($name,'::') !== false){
				$name = str_replace(['\\','::'],['/','/'],substr($name,4));
			}

			$pos = strpos($name,'.');
			if($pos===false){
				$file = $name;
				$k = array();
			}else{
				$file = substr($name,0,$pos);
				$k = explode('.',substr($name,$pos+1));
			}
			if(!isset($_conf[$file])){
				$_conf[$file] = array();
				$path = _APP_.'/../config/'.$file.'.php';
				if(is_file($path)){
					$_conf[$file] = include($path);
				}
			}
			
			$p = &$_conf[$file];

			for($i=0,$l=count($k);$i!=$l;++$i){
				if(!is_array($p))return;
				$p = &$p[$k[$i]];
			}

			$_ps[$name] = &$p;
			return $p;
		break;
		case 2:
			if(strpos($name,'::') !== false){
				$name = str_replace(['\\','::'],['/','/'],substr($name,4));
			}
			$path = _APP_.'/../config/'.$name.'.php';
			return F($path,'<?php return '.var_export(func_get_arg(1),true).';');
		break;
	}
}

function env($name,$default = null){
	static $_env = [];
	static $_p = [];
	if(is_string($name)){
		if(!empty($_p[$name]))return $_p[$name];

		$k = explode('.',$name);
		
		$p = &$_env;

		for($i=0,$l=count($k);$i!=$l;++$i){
			if(!is_array($p) || !isset($p[$k[$i]]))return $default;
			$p = &$p[$k[$i]];
		}

		$_p[$name] = &$p;
		return $p;
	}else{
		$_env = array_merge($_env,$name);
	}
}

// 格式化url
function url_path($path){
	$path=str_replace('\\','/',$path);
	$last='';
	while($path!=$last){
		$last=$path;
		$path=preg_replace('/\/[^\/]+\/\.\.\//','/',$path);
	}
	$last='';
	while($path!=$last){
		$last=$path;
		$path=preg_replace('/([\.\/]\/)+/','/',$path);
	}
	return $path;
}

function http(){
	switch(func_num_args()){
		case 1:
			$k = explode('.',func_get_arg(0));
		
			$d = \Co::getContext();
			$_k = array_shift($k);
			if(!isset($d[$_k]))return;
			$p = &$d[$_k];
			
			for($i=0,$l=count($k);$i!==$l;++$i){
				if(!is_array($p) || !isset($p[$k[$i]]))return;
				$p = &$p[$k[$i]];
			}
			
			return $p;
		break;
		case 2:
			$k = explode('.',func_get_arg(0));
		
			$d = \Co::getContext();
			$_k = array_shift($k);
			if(!isset($d[$_k]))$d[$_k] = [];
			$p = &$d[$_k];
			
			for($i=0,$l=count($k);$i!==$l;++$i){
				if(!is_array($p) || !isset($p[$k[$i]]))$p[$k[$i]] = [];
				$p = &$p[$k[$i]];
			}
			
			$p = func_get_arg(1);
		break;
	}
}

function app(string $name, array $args = [], bool $newInstance = false){
	return \xaoi\container::getInstance()->make($name, $args, $newInstance);
}

function bind($abstract, $concrete = null){
	return \xaoi\container::getInstance()->bind($abstract, $concrete);
}

function setTimeout($fn){
	switch(func_num_args()){
		case 1:
			return \Swoole\Event::defer(func_get_arg(0));
		break;
		case 2:
			return \Swoole\Timer::after(func_get_arg(1),func_get_arg(0));
		break;
	}
}

function send(){
	return call_user_func_array([app('\xaoi\swoole'),'send'],func_get_args());
}

function db(){
	return call_user_func_array([app('\xaoi\mysql'),'db'],func_get_args());
}

function view(){
	static $tpls = [];
	$p = '';
	$d = [];
	switch(func_num_args()){
		case 0:
			
		break;
		case 1:
			$k = func_get_arg(0);
			if(is_string($k)){
				$p = $k;
			}else{
				$d = $k;
			}
		break;
		case 2:
			$p = func_get_arg(0);
			$d = func_get_arg(1);
		break;
	}

	$r = explode('\\',http('route.class'));
	$m = array_shift($r);
	$f = $m.'/view'.url_path((empty($r)?'':('/'.implode('/',$r))).'/'.(empty($p)?http('route.fn'):$p)).'.php';
	$_f = _APP_.'/'.$f;
	if(!is_file($_f))_exit('no view file');
	if(empty($tpls[$f])){
		$tpls[$f] = include($_f);
	}

	if($tpls[$f] instanceof \Closure){
		echo call_user_func($tpls[$f],$d);
	}else{
		echo 'no view';
	}
}

// 获取或修改文件
function F(){
	switch(func_num_args()){
		case 1:
			return file_get_contents(func_get_arg(0));
		break;
		case 2:
			$p = func_get_arg(0);
			$v = func_get_arg(1);
			if(!is_string($v) && !is_int($v))$v = serialize($v);
			if(!is_dir(dirname($p)))mkdir(dirname($p),0755,true);
			file_put_contents($p,$v);
		break;
	}
}

// 退出输出
function _exit(){
	switch(func_num_args()){
		case 0:
			throw new \Exception;
		break;
		case 1:
			$arg = func_get_arg(0);
			throw new \Exception(is_string($arg)?$arg:json($arg));
		break;
		default:
			throw new \Exception(json(func_get_args()));
		break;
	}
}

// 输出到命令行
function _echo(){
	if(!APP_DEBUG)return;
	$args = func_get_args();
	foreach($args as $v){
		$url = env('app.echo');
		$str = is_null($v)?'NULL':(is_bool($v)?($v?'TRUE':'FALSE'):print_r($v,true));
		if(empty($url))
			file_put_contents('php://stdout',$str."\n");
		else
			_fsockopen($url,$str,true);
    }
}

function p(){
	if(!APP_DEBUG)return;
	$args = func_get_args();
	foreach($args as $v){
		$str = is_null($v)?'NULL':(is_bool($v)?($v?'TRUE':'FALSE'):print_r($v,true));
		file_put_contents('php://stdout',$str."\n");
    }
}

// json编码
function json($d){
	return json_encode($d,JSON_UNESCAPED_UNICODE);
}

// json编码-输出-退出
function _json($d){
	_exit(json_encode($d,JSON_UNESCAPED_UNICODE));
}

// url-base64
function url_base64_encode($string) {
	$data = base64_encode($string);
	$data = str_replace(array('+','/','='),array('-','_',''),$data);
	return $data;
}

function url_base64_decode($string) {
	$data = str_replace(array('-','_'),array('+','/'),$string);
	$mod4 = strlen($data) % 4;
	if ($mod4) {
		$data .= substr('====', $mod4);
	}
	return base64_decode($data);
}

// 获取url数据-file_get_contents
function post($url,$data=' ',$cookie = ''){
	if(is_array($data)){
		$data = http_build_query($data);
		if(empty($data))$data=' ';
	}
	if(is_array($cookie)){
		foreach($cookie as $k => &$v){
			$v = $k.'='.$v;
		}
		$cookie = implode('; ',$cookie);
	}
	return file_get_contents($url,false,stream_context_create(array('http'=>array(
		'method'=>'POST',
		'header'=>
			'Content-type: application/x-www-form-urlencoded'."\r\n".
			($cookie != ''?('Cookie: '.$cookie."\r\n"):'').
			'Content-length: '.strlen($data)."\r\n",
		'content'=>$data))));
}

// 获取url数据-curl
function _curl($url, $data = array(),$cookie = ''){
	if(is_array($cookie)){
		foreach($cookie as $k => &$v){
			$v = $k.'='.$v;
		}
		$cookie = implode(';',$cookie);
	}
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	if(!empty($data)){
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	}
	if(!empty($cookie)){
		curl_setopt($ch, CURLOPT_COOKIE, $cookie);
	}
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	curl_setopt($ch, CURLOPT_HEADER, false);
	curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
	$r = curl_exec($ch);
	curl_close($ch);
	return $r;
}

// 获取url数据-fsockopen-可异步
function _fsockopen($url,$post = array(),$exit = false,$referer = ''){
	$par = parse_url($url);
	if($par['scheme'] === 'http' || $par['scheme'] === 'https'){
		if( $par['scheme'] === 'https'){
			$ssl = 'ssl:// ';
			if(!isset($par['port']))$par['port'] = 443;
		}else{
			$ssl = '';
			if(!isset($par['port']))$par['port'] = 80;
		}

		if(isset($par['path'])){
			$path = substr($url,strpos($url,'/',strpos($url,$par['host'])+strlen($par['host'])));
		}else{
			$path = '/';
		}

		if($post) {
			if(is_array($post))
			{
				$post = http_build_query($post);
			}
			$out = "POST ".$path." HTTP/1.0\r\n";
			$out .= "Accept: */*\r\n";
			if(!empty($referer))$out .= "Referer: ".$referer."\r\n";
			$out .= "Accept-Language: zh-cn\r\n";
			$out .= "Content-Type: application/x-www-form-urlencoded\r\n";
			$out .= "Host: ".$par['host']."\r\n";
			$out .= 'Content-Length: '.strlen($post)."\r\n";
			$out .= "Connection: Close\r\n";
			$out .= "Cache-Control: no-cache\r\n\r\n";
			$out .= $post;
		} else {
			$out = "GET ".$path." HTTP/1.0\r\n";
			$out .= "Accept: */*\r\n";
			if(!empty($referer))$out .= "Referer: ".$referer."\r\n";
			$out .= "Accept-Language: zh-cn\r\n";
			$out .= "Host: ".$par['host']."\r\n";
			$out .= "Connection: Close\r\n";
			$out .= "Cache-Control: no-cache\r\n\r\n";
		}

		$fp = fsockopen($ssl.$par['host'], $par['port'], $errno, $errstr, 30);
		if(!$fp)return false;

		fwrite($fp, $out);
		if($exit)return;
		$r = '';
		while (!feof($fp)) {
			$r .= fgets($fp, 128);
		}
		fclose($fp);
		return $r;
	}
}

// 批量获取url数据
function _curls($arr,$fn = null){
	$chs = array();
	foreach($arr as $url => &$v){
		$chs[$url] = curl_init();
		$ch = &$chs[$url];
		curl_setopt($ch, CURLOPT_URL, $url);
		if(!empty($v['data'])){
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $v['data']);
		}
		if(!empty($v['cookie'])){
			if(is_array($v['cookie'])){
				foreach($v['cookie'] as $k2 => &$v2){
					$v2 = $k2.'='.$v2;
				}
				$v['cookie'] = implode(';',$v['cookie']);
			}
			curl_setopt($ch, CURLOPT_COOKIE, $v['cookie']);
		}
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
	}
	unset($ch);
	unset($v);
	$mh = curl_multi_init();
	foreach($chs as &$ch){
		curl_multi_add_handle($mh, $ch);
	}
	unset($ch);

	$active = null;
	do{
		while(($mrc = curl_multi_exec($mh, $active)) == CURLM_CALL_MULTI_PERFORM);
		if($mrc != CURLM_OK)break;
		while ($done = curl_multi_info_read($mh)) {
			$url = array_search($done['handle'],$chs);
			$arr[$url]['info'] = curl_getinfo($done['handle']);
			$arr[$url]['error'] = curl_error($done['handle']);
			$arr[$url]['result'] = curl_multi_getcontent($done['handle']);
			if(is_callable($fn))$fn($arr[$url]);
			curl_multi_remove_handle($mh, $done['handle']);
			curl_close($done['handle']);
		}
		if($active > 0)curl_multi_select($mh);
	}while($active);
	curl_multi_close($mh);
	return $arr;
}

function _header(){
	switch(func_num_args()){
		case 0:
			return http('req')->header;
		break;
		case 1:
			$req = http('req');
			if(!empty($req->header[func_get_arg(0)]))return $req->header[func_get_arg(0)];
		break;
		case 2:
			return http('res')->header(func_get_arg(0),func_get_arg(1));
		break;
	}
}

function task(){
	app('\xaoi\swoole')->task(func_get_args());
}

/**
 * 指定进程id调用
 *
 * @param int $id
 * @param string $fn
 * @param ... $args
 * @return void
 */
function task_id(){
	$args = func_get_args();
	$id = array_shift($args);
	app('\xaoi\swoole')->task($args,$id);
}

function finish(){
	app('\xaoi\swoole')->finish(func_get_args());
}

// 获取并过滤变量
function I($_p,$data = null){
	if(is_array($_p)){
		foreach($_p as &$v){
			$v = I($v,$data);
		}
	}elseif(!is_string($_p)){
		return $_p;
	}else{
		$tmp = explode('?',$_p,2);
		$zz = isset($tmp[1])?$tmp[1]:null;
		$tmp = explode('=',$tmp[0],2);
		$def = isset($tmp[1])?$tmp[1]:null;
		$tmp = explode('/',$tmp[0],2);
		$type = isset($tmp[1])?$tmp[1]:null;
		$a = explode('.',$tmp[0]);
		$key = array_shift($a);
		switch($key){
			case 'header':
				$p = http('req')->header;
			break;
			case 'server':
				$p = http('req')->server;
			break;
			case 'get':
				$p = http('req')->get;
				if(empty($p))$p = [];
			break;
			case 'post':
				$p = http('req')->post;
				if(empty($p))$p = [];
			break;
			case 'file':
				$p = http('req')->files;
				if(empty($p))$p = [];
			break;
			case 'cookie':
				$p = http('req')->cookie;
				if(empty($p))$p = [];
			break;
			case 'data':
				$p = $data;
			break;
			default:
				return $_p;
			break;
		}
		for($i=0,$l=count($a);$i!=$l;++$i){
			if(!is_array($p)){
				break;
			}else{
				$p = &$p[$a[$i]];
			}
		}
		$_p = $p;
		if(is_null($_p) || $_p === ''){
			if(is_null($def)){
				$code = config('xaoi/state_code.app.input');
				if(APP_DEBUG)$code[] = 'input error:'.$tmp[0];
				_exit($code);
			}else{
				$_p = $def;
			}
		}elseif(!is_null($zz)){
			if(1 !== preg_match($zz,(string)$_p)){
				if(is_null($def)){
					$code = config('xaoi/state_code.app.input');
					if(APP_DEBUG)$code[] = 'input error:'.$tmp[0];
					_exit($code);
				}else{
					$_p = $def;
				}
			}
		}
		switch($type){
			case 'i':
			case 'int':
				$_p = (int)$_p;
			break;
			case 'I':
				$_p = (int)$_p;
				if($_p < 0)$_p = 0;
			break;
			case 'f':
			case 'float':
				$_p = (float)$_p;
			break;
			case 'd':
			case 'double':
				$_p = (double)$_p;
			break;
			case 'n':
			case 'number':
				preg_match_all('/^\d+/',$_p,$arr);
				$_p = empty($arr[0][0])?'0':ltrim($arr[0][0],'0');
			break;
			case 's':
			case 'string':
				$_p = htmlspecialchars($_p);
			break;
			case 'b':
			case 'bool':
				$_p = (bool)$_p;
			break;
			case 'a':
			case 'array':
				$_p = (array)$_p;
			break;
			case 'o':
			case 'object':
				$_p = (object)$_p;
			break;
			case 'json':
				$_p = empty($_p)?null:json_decode($_p,true);  
			break;
			case 'file':
				if(empty($_p)){
					$_p = null;
				}else{
					$d = json_decode($_p,true);
					if(is_array($d)){
						$_p = form(I('file'),'file_'.implode('_',$a),$d);
					}else{
						$_p = null;
					}
				}
			break;
		}
	}
	return $_p;
}

function _I($a,$d){
	$r = [];
	foreach($a as $k => $v){
		if(is_array($v)){
			$tmp = _I($v,empty($d[$k])?[]:$d[$k]);
			if(is_null($tmp))_exit(-1004,'input error:'.(APP_DEBUG?$tmp[0]:''));
			$r[$k] = $tmp;
		}else{
			$tmp = explode('?',$v);
			$zz = empty($tmp[1])?null:$tmp[1];
			$tmp = explode('=',$tmp[0]);
			$type = $tmp[0];
			$def = isset($tmp[1])?$tmp[1]:null;
			if(isset($d[$k])){
				$_p = $d[$k];
				switch($type){
					case 'i':
					case 'int':
						$_p = (int)$_p;
					break;
					case 'I':
						$_p = (int)$_p;
						if($_p < 0)$_p = 0;
					break;
					case 'f':
					case 'float':
						$_p = (float)$_p;
					break;
					case 'd':
					case 'double':
						$_p = (double)$_p;
					break;
					case 'n':
					case 'number':
						preg_match_all('/^\d+/',$_p,$arr);
						$_p = empty($arr[0][0])?'0':ltrim($arr[0][0],'0');
					break;
					case 's':
					case 'string':
						$_p = (string)$_p;
						if(!is_null($zz)){
							if(1 !== preg_match($zz,$_p)){
								if(is_null($def)){
									_exit(-1004,'input error:'.(APP_DEBUG?$tmp[0]:''));
								}else{
									$_p = $def;
								}
							}
						}
					break;
					case 'b':
					case 'bool':
						$_p = (bool)$_p;
					break;
					case 'a':
					case 'array':
						$_p = (array)$_p;
					break;
					case 'o':
					case 'object':
						$_p = (object)$_p;
					break;
					case 'json':
						$_p = empty($_p)?null:json_decode($_p,true);  
					break;
					case 'file':
						if(empty($_p)){
							$_p = null;
						}else{
							$d = json_decode($_p,true);
							if(is_array($d)){
								$_p = form(I('file'),'file_'.implode('_',$a),$d);
							}else{
								$_p = null;
							}
						}
					break;
				}
				$r[$k] = $_p;
			}else{
				if($def === null){
					_exit(-1004,'input error:'.(APP_DEBUG?$tmp[0]:''));
				}else{
					$r[$k] = $def;
				}
			}
		}
	}
	return $r;
};

function form($f,$n,$d){
	foreach($d as $k => $v){
		$_n = $n.'_'.$k;
		if(is_array($v)){
			$d[$k] = form($f,$_n,$v);
		}else{
			if(!empty($f[$_n])){
				$d[$k] = $f[$_n];
			}
		}
	}
	return $d;
}

function base64_file($str){
	if(strpos($str,'data:') !== 0)return;
	list($tem,$body)	= explode(',',$str,2);
	list($t1,$t2)		= explode(':',$tem,2);
	list($gs,$type)		= explode(';',$t2,2);

	list($d,$f) = explode('/',$gs,2);

	$index = '';
	switch($d){
		case 'image':
			switch($f){
				case 'jpeg':
				case 'jpg':
					$index = '.jpg';
				break;
				case 'png':
					$index = '.png';
				break;
				case 'gif':
					$index = '.gif';
				break;
				case 'bmp':
					$index = '.bmp';
				break;
				default:
					$index = '.jpg';
				break;
			}
		break;
	}

	$r = null;

	switch($type){
		case 'base64':
			$r = ['name'=>dec62(str_replace('.','',''.microtime(true)).mt_rand(1000, 9999)) . $index,'file'=>base64_decode($body)];
		break;
	}

	return $r;
}

//10进制转62进制
function dec62($n) {
	$base = 62;
	$index = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	$ret = '';
	for($t = floor(log10($n) / log10($base)); $t >= 0; $t --) {
	$a = floor($n / pow($base, $t));
	$ret .= substr($index, $a, 1);
	$n -= $a * pow($base, $t);
	}
	return $ret;
}

//62进制转10进制
function dec10($s) {  
	$base = 62;  
	$index = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';  
	$ret = 0;  
	$len = strlen($s) - 1;  
	for($t = 0; $t <= $len; $t ++) {
	$ret += strpos($index, substr($s, $t, 1)) * pow($base, $len - $t);  
	}  
	return $ret;
}

// 设置cookie
function cookie(){
	switch(func_num_args()){
		case 0:
			return http('req')->cookie;
		break;
		case 1:
			$k = func_get_arg(0);
			if(isset(http('req')->cookie[$k])){
				return http('req')->cookie[$k];
			}elseif(is_null($k) || $k == ''){
				foreach(http('req')->cookie as $key => &$value){
					http('res')->cookie($key,null,null,'/');
				}
				$c = http('req')->cookie;
				http('req')->cookie = array();
				return $c;
			}
		break;
		case 2:
			$k = func_get_arg(0);
			$v = func_get_arg(1);
			if(!is_null($k)){
				http('req')->cookie[$k] = $v;
				http('res')->cookie($k,$v,null,'/');
			}
		break;
		case 3:
			$k = func_get_arg(0);
			$v = func_get_arg(1);
			$o = func_get_arg(2);
			if(!is_null($k)){
				http('req')->cookie[$k] = $v;
				if(is_numeric($o)){
					http('res')->cookie($k,$v,$o,'/');
				}elseif(is_string($o)){
					http('req')->cookie[$k] = $v;
					http('res')->cookie($k,$v,null,$o);
				}elseif(is_array($o)){
					http('res')->cookie(
						$k,
						$v,
						!empty($o['expire']) && is_numeric($o['expire'])?$o['expire']:(time()+3600*24),
						!empty($o['path'])?$o['path']:'/',
						!empty($o['domain'])?$o['domain']:'',
						!empty($o['secure'])?$o['secure']:''
					);
				}
			}
		break;
		case 4:
			$k = func_get_arg(0);
			$v = func_get_arg(1);
			if(!is_null($k)){
				http('req')->cookie[$k] = $v;
				http('res')->cookie($k,$v,func_get_arg(2),func_get_arg(3));
			}
		break;
	}
}

function redis(){
	static $dbs = [];
	switch(func_num_args()){
		case 0:
			return;
		break;
		case 1:
			$dbname = 'default';
			$tab = func_get_arg(0);
		break;
		case 2:
			$dbname = func_get_arg(0);
			$tab = func_get_arg(1);
		break;
	}
	if($tab instanceof \Closure){
		$obj = app('\xaoi\redis');
		try{
			$redis = $obj->chan($dbname);
			$r = call_user_func($tab,$redis);
			$obj->chan($dbname,$redis);
			return $r;
		}catch(\Exception $e){
			$obj->chan($dbname,$redis);
			if(APP_DEBUG)_echo($e->getMessage());
			return config('xaoi/state_code.redis.error');
		}
	}else{
		if(empty($dbs[$dbname][$tab]))$dbs[$dbname][$tab] = new \xaoi\redis_db($dbname,$tab);
		return $dbs[$dbname][$tab];
	}
}


// 设置session
function session(){
	$sess = http('session');
	if(empty($sess))_exit('no open session');
	return call_user_func_array([$sess,'session'],func_get_args());
}

function location($url){
	http('res')->status(302);
	_header('Location',$url);
	_exit();
}

//判断ssl
function is_ssl() {
    if(_header('https') == 'on'){
        return true;
    }
    return false;
}

// 是否ajax访问
function is_ajax($is = false){
	if(!empty(http('req')->header["ajax"]) && http('req')->header["ajax"] === 'XAOI')return true;
	if($is)_exit();
	return false;
}

//分页工具
function page($db,$where = '',$field = '',$order = '',$group = ''){
	$page = I('post.page/I');
	$limit = I('post.limit/I');
	try{
		$_count = $db->get($where,'count(*) as count',1);
		$count = empty($_count)?0:$_count['count'];
		if($page < 1)$page = 1;
		if($limit < 1)$limit = 1;
		if($limit > 100)$limit = 100;
		$sum = ceil($count/$limit);
		$data = $db->get($where,$field,$group,$order,[($page-1)*$limit,$limit]);
	}catch(\Exception $e){
		
		return [
			'code'=> 0,
			'msg'=> -500,
			'limit'=> 0,
			'count'=> 0,
			'data'=>[]
		];
	}

	return [
		'code'=> 0,
		'msg'=> '',
		'limit'=> $limit,
		'count'=> $count,
		'data'=>$data
	];
}
//分页工具
function page_g($db,$where = '',$field = '',$order = '',$group = ''){
	$page = I('post.page/I');
	$limit = I('post.limit/I');
	try{
		$_count = $db->get($where,'count(*) as count',$group,$order);
		
		$count = empty($_count)?0:count($_count); //empty($_count)?0:$_count['count'];
		if($page < 1)$page = 1;
		if($limit < 1)$limit = 1;
		if($limit > 100)$limit = 100;
		$sum = ceil($count/$limit);
		$data = $db->get($where,$field,$group,$order,[($page-1)*$limit,$limit]);
	}catch(\Exception $e){
		
		return [
			'code'=> 0,
			'msg'=> -500,
			'limit'=> 0,
			'count'=> 0,
			'data'=>[]
		];
	}

	return [
		'code'=> 0,
		'msg'=> '',
		'limit'=> $limit,
		'count'=> $count,
		'data'=>$data
	];
}

function order($oa){
	$order = I('post.order/s=');
	$desc = I('post.desc/I=');
	$_order = [];
	if(in_array($order,$oa)){
		$_order[$order] = $desc === 0?false:true;
	}
	return $_order;
}

//分页工具-获取ids
function page_ids(&$d){
	$n = func_num_args();
	if($n === 2){
		$k = func_get_arg(1);
		$ids = [];
		foreach($d as $v){
			if(!in_array($v[$k],$ids))$ids[] = $v[$k];
		}
		return $ids;
	}else{
		$k = func_get_arg(1);
		$l = func_get_arg(2);
		$i = func_get_arg(3);
		$p = func_get_arg(4);
		$t = [];
		foreach($l as $v){
			$t[$v[$i]] = $v;
		}
		foreach($d as &$v){
			foreach($p as $kp => $vp){
				$v[$kp] = is_string($vp)?$t[$v[$k]][$vp]:$vp($t[$v[$k]]);
			}
		}
		unset($v);
		return $d;
	}
}

//协程
/*
	_go([
		fn()=>{},
		fn()=>{},
	]);
*/
function _go($args){
	$chans = new \chan(count($args));
	foreach($args as $k => $v){
		go(function()use($k,$v,$chans){
			$chans->push([$k,call_user_func($v)]);
		});
	}
	$r = [];
	foreach($args as $k => $v){
		$d = $chans->pop();
		$r[$d[0]] = $d[1];
	}
	return $r;
}