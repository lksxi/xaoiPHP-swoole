# 数据库

| 短连接 | 长连接 |
| :-----| :---- |
| 数据库对象 | 数据库对象 |
| 数据表对象 |  |

> ### 短连接-数据库对象


| 方法名 | 介绍 |
| :-----| :---- |
| query | 执行sql语句，默认为查询 |
| _query | 预处理执行sql语句，默认为查询 |
| is_table | 查询表是否存在 |

* 获取

```php
db();   //默认获取 [default] 数据库
或
db('default',null);
```

* query

```php
//方式1
$res = db('default',null)->query('select * from 表名');

//方式2
$db = db('default',null);
$res = $db->query('select * from 表名');
```

* _query

```php
//方式1
$res = db('default',null)->_query('select * from 表名 where id=? and name = ?',[5,'xaoiphp']);

//方式2
$db = db('default',null);
$res = $db->_query('select * from 表名 where id=? and name = ?',[5,'xaoiphp']);
```

* is_table

```php
$res = db('default',null)->is_table('表名');
```

> ### 短连接-数据表对象


| 方法名 | 介绍 |
| :-----| :---- |
| add | 添加 |
| get | 查询 |
| set | 修改 |
| del | 删除 |
| _add | 协程版，返回一个匿名函数，调用获取数据 |
| _set | 协程版，返回一个匿名函数，调用获取数据 |
| _get | 协程版，返回一个匿名函数，调用获取数据 |
| _del | 协程版，返回一个匿名函数，调用获取数据 |

* 获取

```php
user表
db('user');   //默认获取 [default] 数据库
或
db('default','user');
```

* add

```php
$data = [
    'id'    => 5,
    'name'  => 'xaoiphp'
];
$ins_id = db('user')->add($data);     //返回自增id
```

* get 

```php
// 根据参数数量匹配

// 0个参数 获取表所有数据
$list = db('user')->get();

// 1个参数
//参数是 数字 或 [1,10] 数组 为 limit 否则为 where

//数字
$list = db('user')->get(1);         //获取1条 是一个1维数组
$list = db('user')->get(10);        //获取多条 是一个2维数组
$list = db('user')->get([0,10]);    //获取多条 是一个2维数组

//查询条件
$where = [
    'id'    => 5,
    'name'  => 'xaoiphp'
];
$list = db('user')->get($where);    //获取匹配条件的数据 是一个2维数组

// 2个参数
$list = db('user')->get($where,$limit);
或
$list = db('user')->get($where,$order);

// 3个参数
$list = db('user')->get($where,$order,$limit);
或
$list = db('user')->get($where,$order,$group);

// 4个参数
$list = db('user')->get($where,$order,$group,$limit);
或
$list = db('user')->get($where,$order,$group,$order);

// 5个参数
$list = db('user')->get($where,$order,$group,$order,$limit);

```

* set

```php
// 根据参数数量匹配

// 1个参数 设置所有的数据
$set = [
    'id'    => 5,
    'name'  => 'xaoiphp'
];
$up_count = db('user')->set($set);

// 2个参数 设置匹配的数据
$where = [
    'id'    => 5,
];
$set = [
    'name'  => 'xaoiphp'
];
$up_count = db('user')->set($where,$set);
```
* del

```php
// 1个参数 删除匹配的数据
$where = [
    'id'    => 5,
];
$up_count = db('user')->del($where);
```

?> 协程操作
```php
//并发操作数据
$data = [
    'id'    => 5,
    'name'  => 'xaoiphp'
];
$fn1 = db('user')->_add($data);
$data = [
    'id'    => 6,
    'name'  => 'xaoiphp6'
];
$fn2 = db('user')->_add($data);
$data = [
    'id'    => 7,
    'name'  => 'xaoiphp7'
];
$fn3 = db('user')->_add($data);

$ins_id1 = $fn1();
$ins_id2 = $fn2();
$ins_id3 = $fn3();

echo '批量添加成功';
```

> ### 长连接-数据库对象

| 方法名 | 介绍 |
| :-----| :---- |
| begin | 开启事务 |
| rollback | 回滚 |
| commit | 提交 |
| query | 执行sql语句，默认为查询 |
| _query | 预处理执行sql语句，默认为查询 |
| is_table | 查询表是否存在 |
| add | 添加 |
| get | 查询 |
| set | 修改 |
| del | 删除 |

* 获取

```php
user表
db(true);   //默认获取 [default] 数据库
或
db('default',true);
```

* query / _query / is_table 与短连接类似
* add / get / set / del 多了第一个参数 表名