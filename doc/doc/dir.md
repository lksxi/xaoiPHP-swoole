# 目录结构

```
www WEB部署目录
├─app                   应用目录
│  ├─config             配置目录
│  └─index              模块目录
│     ├─inde.php        控制器文件
│     ├─...
│     │
│     └─view            模板目录
│
├─www                   WEB目录（对外访问目录）
│  └─static             静态资源
│     ├─index           模块目录
│     │  └─index        控制器目录
│     │     └─index     方法目录(模板使用__THIS__对应目录)
│     │
│     ├─html            缓存的js模板文件
│     └─lib             js工具
│        ├─t.js         控制模板显示文件
│        └─u.js         js版url函数(模板添加__URL__)
│
├─src
│  └─xaoi               XaoiPHP类库目录
│
├─vendor                Composer类库目录
├─.example.env          环境变量示例文件
├─composer.json         composer 定义文件
└─xaoi                  命令行入口文件

```