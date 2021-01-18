<?php

return [

	//启动服务器前执行
	'onstart'	=> function(){

	},

	//启动Worker进程后执行
	'onworkerstart'	=> function($worker_id){

		app('\xaoi\redis');
		app('\xaoi\mysql');

		if($worker_id === 0){

		}

	},

	//启动Task进程后执行
	'ontaskstart'	=> function($worker_id){

		app('\xaoi\redis');
		app('\xaoi\mysql');

		if($worker_id === config('xaoi/swoole.worker_num')){

		}
	}
];