<?php
namespace xaoi;

class app{
	function __construct(){
		include __DIR__.'/function.php';

		//设置环境变量
		$this->set_env();

		//定义常量
		define('APP_DEBUG',env('app.debug',false));

		define('__ROOT__','');
		define('__STATIC__',__ROOT__.'/static');
		define('_APP_',url_path(__DIR__.'/../../app'));
		define('_START_',url_path(__DIR__.'/../../'));
		define('_ROOT_',url_path(__DIR__.'/../../'.env('app.web_path','www')));
		define('_STATIC_',_ROOT_.__STATIC__);
		define('__UPLOAD__','/upload');
		define('_UPLOAD_',_ROOT_.__UPLOAD__);

		//设置时区
		date_default_timezone_set(env('app.DEFAULT_TIMEZONE'));

		//错误输出/记录
		if(APP_DEBUG){
			ini_set('display_errors', 'On');
			set_error_handler(function($errno, $errstr, $errfile, $errline){
				$f = getcwd() . '/log/php/'.date('y_n/j').'.txt';
				if(!is_dir(dirname($f)))mkdir(dirname($f),0755,true);
				$errlog = '['.date('Y-m-d H:i:s').'] '.$errstr.' in '.$errfile.' on line '.$errline;
				file_put_contents($f,$errlog."\n",FILE_APPEND);
				_echo($errlog);
				_exit(config('xaoi/state_code.app.error'));
			});
		}else{
			ini_set('display_errors', 'Off');
		}

		\xaoi\container::init();
		app('\xaoi\swoole')->start(env('app.host'),env('app.port'),env('app.swoole_mode',SWOOLE_PROCESS));
	}

	private function set_env(){
		if (is_file(__DIR__ . '/../../.env')) {
			$env = F(__DIR__ . '/../../.env');
			$env = preg_replace('/\/\*[\S\s]*?\*\//','',$env);
			$env = preg_replace('/\/\/[^"]*\n/','',$env);
			$env = json_decode($env,true);
			if(is_null($env)){
				echo '环境变量解析失败';
				exit;
			}
			
			env($env);
		}
	}
}

//开关-第一次请求创建
class base{

}

//开关-每次请求创建
class init{

}

//开关-ws请求创建(第一次请求创建)
class websocket{

}

//开关-中间件(第一次请求创建)
class fns{

}