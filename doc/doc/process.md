# 请求流程

> ### Manager进程
* 载入Composer的自动加载autoload文件
* 加载全局函数
* 加载环境变量文件`.env`、常量配置
* 初始化容器
* 启动服务器

> ### Worker进程

* 初始化 `redis,mysql,mysql从库` 对象
* 绑定用户对象
* 监听 `request` 事件

> ### HTTP请求流程

?> 大致的标准请求流程如下：

* 获取路由(class,function,args)
* 创建请求作用域http(绑定http,session)
* 获取路由对应对象
* 执行输出