# 响应输出

?> 最简单的响应输出是直接`echo`或者控制器操作方法中返回一个字符串，例如：

```php
<?php
namespace app\index;

class index extends \xaoi\init{

    public function hello($name = 'xaoiphp'){

        //echo 'Hello,'.$name;
        return 'Hello,'.$name;
    }

}

```

?> 输出一个JSON数据给客户端（或者AJAX请求），可以使用：
```php
<?php
namespace app\index;

class index extends \xaoi\init{

    public function hello($name = 'xaoiphp'){

        $data = [0,'Hello,'.$name];

        //echo json($data);
        return json($data);
    }

}

```
