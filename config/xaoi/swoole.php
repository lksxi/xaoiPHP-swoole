<?php

return [
	'package_max_length'	=> 1024 * 1024 * 20,		//协议最大长度-20M
	'worker_num'			=> swoole_cpu_num() + 1,
	'task_worker_num'		=> swoole_cpu_num(),
	'task_enable_coroutine'	=> true,
	'max_request'			=> 1000,					//请求一定次数关闭进程
	'upload_tmp_dir'		=> url_path(__DIR__.'/../../runtime/data/upload_tmp'),
];