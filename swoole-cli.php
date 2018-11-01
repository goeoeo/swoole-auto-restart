<?php

/**
 * 此类用于实现监听项目文件改变后自动重启swoole-server
 * 依赖: php inotify扩展
 * Class SwooleCli
 */
class SwooleCli
{
    //监听的事件
    const WATCH_CHANGED=IN_MODIFY | IN_CLOSE_WRITE | IN_MOVE | IN_CREATE | IN_DELETE;

    //不需要监测的文件夹
    const SKIP_DIR=['/vendor','/bootstrap','/storage','/tests','/swoolecli'];

    //重启间隔,防止频繁重启
    const RESTART_INTERVAL=1000;//毫秒

    //重启服务器的命令
    const RESTART_COMMAND=[
        '/usr/bin/php',
        __DIR__.'/artisan',
        'websocket:start'
    ];

    private $fd;//inotify句柄
    private $rootDir;//项目根目录
    private $skipDir;//不需要监听的目录
    private $dirNum=0;//记录目录条数

    private $swooleServerPid;//swoole服务器的进程id,用于重启杀死子类进程
    private $lastStartTime;//上次重启时间



    public function __construct()
    {
        $this->fd=inotify_init();

        $this->rootDir=str_replace('/swoolecli','',__DIR__);


        //设置不需要监听的目录
        $this->setSkipDir();

        //开始扫描目录
        $this->scanDirRecursion($this->rootDir);

        //监听单个目录
        //$this->setWatch($this->rootDir);

        swoole_process::signal(SIGCHLD, function($sig) {
            //必须为false，非阻塞模式
            while($ret =  swoole_process::wait(false)) {
                echo '进程[pid='.$ret['pid'].']已被回收'.PHP_EOL;
            }
        });
    }

    public function __get($name)
    {

     return isset($this->$name)?$this->$name:null;
    }


    /**
    * 设置需要不需要监听的目录
    */
    private function setSkipDir()
    {

        $this->skipDir=array_map(function($v){
            return $this->rootDir.$v;
        },self::SKIP_DIR);
    }

    /**
    * 递归扫描目录,并加上监听
    */
    private function scanDirRecursion($dir)
    {
        if(!is_dir($dir) || in_array($dir,$this->skipDir)) return;
        //是目录加监听
        $this->setWatch($dir);

        $dirArr=scandir($dir);

        if(!is_array($dirArr))return;

        foreach ($dirArr as $v){
            if($v=='.' || $v=='..') continue;

            $newPath=$dir.'/'.$v;

            if(!is_dir($newPath)) continue;

            $this->dirNum++;
            //递归调用自己
            $this->scanDirRecursion($newPath);
        }

    }

    /**
    * 设置监听
    */
    public function setWatch($dir)
    {
        //监听文件
        inotify_add_watch($this->fd, $dir, self::WATCH_CHANGED);

        if($this->dirNum==1){
            //加入到swoole的事件循环中
            swoole_event_add($this->fd, function ($fd) {
                $events = inotify_read($fd);
                if ($events) {
                    $mic=$this->microtime();
                    //防止频繁重启
                    if($mic-$this->lastStartTime>self::RESTART_INTERVAL){
                        $this->restart();
                        $this->lastStartTime=$mic;
                    }
                }

            });
            $this->restart();
        }

    }


    /**
    * 重启swoole脚本
    */
    public function restart()
    {
        $this->swooleServerPid && swoole_process::kill($this->swooleServerPid);

        echo '服务器重启中...'.PHP_EOL;

        //开一个进程来进行重启
        $process = new swoole_process(function(swoole_process $worker){

            $worker->exec(self::RESTART_COMMAND[0],[self::RESTART_COMMAND[1],self::RESTART_COMMAND[2]]);

        }, false, false);

        $this->swooleServerPid=$process->start();

        echo '重启成功,pid:'.$this->swooleServerPid.PHP_EOL;

    }

    public function microtime()
    {
        $mic=microtime(true);

        $mic=substr($mic,2);
        return $mic*1000;
    }





}

 $swooleCli=new SwooleCli();

 //echo $swooleCli->dirNum;


