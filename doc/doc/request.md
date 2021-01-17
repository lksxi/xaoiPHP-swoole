# 请求

> ### HTTP头信息
```php
//获取全部
$info = _header();
print_r($info);

//获取单个
$info = _header('request_uri');
print_r($info);

//设置HTTP头
_header('Location','/');

```

> ### 参数绑定

?> 参数绑定是把当前请求的变量作为操作方法的参数直接传入，参数绑定并不区分请求类型。

参数绑定方式是按照变量顺序进行绑定
```php
//app/index/inde.php

<?php
namespace index;

class index extends \xaoi\init{

    public function hello($id,$name)
    {

        return 'id:'.$id.' name:'.$name;
    }

}

```

> URL的访问地址是：
```php
http://127.0.0.1/index/index/hello-5/xaoi.html
```

