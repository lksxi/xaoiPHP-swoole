# 路由

?> 添加控制器并继承开关对象就可以访问

| 可继承类 | 作用域 |
| :-----| :---- |
| \xaoi\base | 对象只会第一次初始化，之后的请求只会再次执行对应函数 |
| \xaoi\init | 每次请求都会重新构造对象执行 |
| \xaoi\websocket | websocket握手连接才能访问 |

>访问网址 http://127.0.0.1/index/hello.html
```php
<?php

class index extends \xaoi\init{

    public function hello()
    {
        //echo 'Hello';
        return 'Hello';
    }

}

```
>访问网址 http://127.0.0.1/index/hello/get.html

```php
<?php
namespace index;

class hello extends \xaoi\init{

    public function get()
    {
        //echo 'Hello get';
        return 'Hello get';
    }

}

```
