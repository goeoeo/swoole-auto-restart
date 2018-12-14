<h1 align="center"> swooleAutoRestart </h1>

<p align="center"> .</p>

## 说明
此扩展包用于,swoole开发,修改代码后自动重启服务
## 依赖PHP扩展
* swoole
    ```bash
    pecl install swoole
    ```
* inotify,此扩展为php文件监听扩展,安装命令如下
    ```bash
    pecl install inotify
    ```


## 安装

```shell
$ composer require phpdic/swooleAutoRestart -vvv
```

## 用法
1.在你的项目根目建立一个php脚本,如:swoole-auto-start.php,内容如下:
```php

require './vendor/autoload.php';

$a = new \Phpdic\SwooleAutoRestart\swooleAutoRestart(__DIR__, '/bin/echo ok');
$a->listen();
```
2.执行你刚刚创建的这个脚本
```bash
/usr/bin/php swoole-auto-start.php
```
## 说明
swooleAutoRestart构造函数注解
```php
 /**
     * swooleAutoRestart constructor.
     * @param string $rootDir 需要监听的项目根目录
     * @param string $startServerCommand 启动swoole服务器的命令
     * @param array $notCheckDir 配置不需要监听的目录
     * @param int $restartInterval 重启间隔
     * @param bool $showDetail 是否展示详细信息
     */
    public function __construct(string $rootDir,string $startServerCommand,array $notCheckDir=[],$restartInterval=1000,$showDetail=false)
    {
```
notCheckDir参数可以配置不需要修改的目录,例如,我的项目是laravel框架
```php
$notCheckDir=['/bootstrap','/storage','/tests']
```

## Contributing

You can contribute in one of three ways:

1. File bug reports using the [issue tracker](https://github.com/phpdic/swooleAutoRestart/issues).
2. Answer questions or fix bugs on the [issue tracker](https://github.com/phpdic/swooleAutoRestart/issues).
3. Contribute new features or update the wiki.

_The code contribution process is not very formal. You just need to make sure that you follow the PSR-0, PSR-1, and PSR-2 coding guidelines. Any new code contributions must be accompanied by unit tests where applicable._

## License

MIT