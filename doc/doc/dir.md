# 目录结构

```
www WEB部署目录
├─config                配置目录
│  ├─index              模块目录
│  └─xaoi               框架目录
│     ├─bind.php            容器绑定
│     ├─route.php           路由配置
│     ├─session.php         session配置
│     ├─state_code.php      状态码配置
│     └─swoole.php          swoole配置
│
├─app                   自动加载根目录
│  ├─index                  模块目录
│  │  └─inde.php                控制器文件
│  │
│  └─xaoi               XaoiPHP类库目录
│     ├─app.php             定义常量
│     ├─container.php       容器类
│     ├─function.php        常用函数
│     ├─mysql.php           mysql类
│     ├─redis.php           redis类
│     ├─session.php         session类
│     └─swoole.php          swoole类
│
├─www                   WEB目录（对外访问目录）
│
├─vendor                Composer类库目录
├─.example.env          环境变量示例文件
├─composer.json         composer 定义文件
└─xaoi                  命令行入口文件

```