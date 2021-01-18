<?php
return [
	'app'	=> [
		'error'			=> [-1000],		//php执行错误
		'input'			=> [-1004]		//缺少参数
	],
	'mysql'	=> [
		'connect'		=> [-2001],	//连接数据库失败
		'error'			=> [-2002],	//执行出错
		'pool_timeout'	=> [-2003]	//获取连接池连接失败
	],
	'redis'	=> [
		'connect'		=> [-3004],	//连接数据库失败
		'error'			=> [-3005],	//执行出错
		'pool_timeout'	=> [-3006]	//获取连接失败
	]
];