# 容器和依赖注入

?> XaoiPHP使用容器来更方便的管理类依赖及运行依赖注入

!> 容器类的工作由xaoi\container类完成，但大多数情况我们只需要通过app助手函数操作。

依赖注入其实本质上是指对类的依赖通过构造器完成自动注入，例如在控制器架构方法和操作方法中一旦对参数进行对象类型约束则会自动触发依赖注入，由于访问控制器的参数都来自于URL请求，普通变量就是通过参数绑定自动获取，对象变量则是通过依赖注入生成。

```php
<?php
namespace index;

class index extends \xaoi\init{

    public function hello(tool $tool,$name)
    {
        return 'Hello,' . $name . '！This is '. $tool->action();
    }

}

class tool{

    function action(){

        return 'action';
    }

}

```

!> 依赖注入的对象参数支持多个，并且和顺序无关。

# 绑定

依赖注入的类统一由容器进行管理，大多数情况下是在自动绑定并且实例化的，支持多种绑定方式。

> ### 绑定类标识
```php
bind('log', 'xaoi\log');
```
?> 绑定的类标识可以自己定义（只要不冲突）。

> ### 绑定闭包

可以绑定一个闭包到容器中
```php
bind('hello', function ($name) {
    return 'hello,' . $name;
});
```

> ### 绑定实例

也可以直接绑定一个类的实例
```php
$log = new xaoi\log;
// 绑定类实例
bind('log', $log);
```

> ### 批量绑定

传递一个数组
```php
bind([
    'log'       => '\xaoi\log',
    'session'   => '\xaoi\session'
]);
```

!> 绑定标识调用的时候区分大小写，系统已经内置绑定了核心常用类库，无需重复绑定

?> 系统内置绑定到容器中的类库包括

| 系统类库 | 容器绑定标识 |
| :-----| :---- |
| \xaoi\redis | \xaoi\redis |
| \xaoi\mysql | \xaoi\mysql |
| \xaoi\swoole | \xaoi\swoole |

# 解析

使用 `app` 助手函数进行容器中的类解析调用，对于已经绑定的类标识，会自动快速实例化

```php
$log = app('log');
```

带参数实例化调用
```php
$log = app('log',['name']);
```

对于没有绑定的类，也可以直接解析
```php
$log = app('\xaoi\log');
```

可以使用相对路径与上一级目录
```php
$log = app(__METHOD__.'/../log');
```

?> 可以使用 `/` 斜杠

!> 调用和绑定的标识必须保持一致（包括大小写）