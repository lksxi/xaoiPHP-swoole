# 模板引擎

> ### 模板渲染

模板渲染的最典型用法是直接使用view方法，不带任何参数：
```php
<?php
namespace index;

class index extends \xaoi\init{

    public function hello()
    {
        
        view();
    }

}
```

当前模板路径为 `app/index/view/index/hello.html`