<?php
/**
 * Auth:chenyu.
 * Mail:phpdi@sina.com
 * Date: 18-12-14
 * Desc:
 */
namespace Phpdic\SwooleAutoRestart;


use Phpdic\SwooleAutoRestart\Exceptions\DependsException;
use Phpdic\SwooleAutoRestart\Exceptions\InvalidArgumentException;

class swooleAutoRestart
{
    //监听的事件
    const WATCH_CHANGED=IN_MODIFY | IN_CLOSE_WRITE | IN_MOVE | IN_CREATE | IN_DELETE;

    //不需要监测的文件夹
    const SKIP_DIR=['/vendor'];


    private $restartCommand;//启动服务命令
    private $showDetail;//是否显示详细信息
    private $restartInterval;//重启间隔,防止频繁重启, 单位毫秒

    private $fd;//inotify句柄
    private $rootDir;//项目根目录
    private $skipDir;//不需要监听的目录
    private $dirNum=0;//记录目录条数

    private $swooleServerPid;//swoole服务器的进程id,用于重启杀死子类进程
    private $lastStartTime;//上次重启时间


    /**
     * swooleAutoRestart constructor.
     * @param string $rootDir 需要监听的项目根目录
     * @param string $startServerCommand 启动swoole服务器的命令
     * @param array $notCheckDir 配置不需要监听的目录
     * @param int $restartInterval 重启间隔
     * @param bool $showDetail 是否展示详细信息
     * @throws InvalidArgumentException
     */
    public function __construct(string $rootDir,string $startServerCommand,array $notCheckDir=[],$restartInterval=1000,$showDetail=false)
    {


        if(!is_dir($rootDir)){
            throw new InvalidArgumentException('Please set a root directory');
        }
        //设置根目录
        $this->rootDir=rtrim($rootDir,'/');

        $this->showDetail=$showDetail;

        $this->restartInterval=$restartInterval;

        //设置重启命令
        $this->setCommand($startServerCommand);

        //设置不需要监测的目录
        $this->setSkipDir($notCheckDir);

    }


    /**
     * 设置需要不需要监听的目录
     * @param array $notCheckDir
     * @return $this
     */
    private function setSkipDir(array $notCheckDir)
    {
        $notCheckDir = array_merge(self::SKIP_DIR, $notCheckDir);

        $this->skipDir=array_map(function($v){
            $v = ltrim($v, '/');
            return $this->rootDir.'/'.$v;
        },$notCheckDir);

        return $this;
    }

    /**
     * 设置重启命令
     * @param $startServerCommand
     * @throws InvalidArgumentException
     */
    private function setCommand($startServerCommand)
    {

        if(!$startServerCommand){
            throw new InvalidArgumentException('Please set a command');
        }

        $startServerCommandArr = explode(' ', $startServerCommand);
        $this->restartCommand[0]=$startServerCommandArr[0];

        unset($startServerCommandArr[0]);
        $this->restartCommand[1]=$startServerCommandArr;

        if (empty($this->restartCommand[0]) || empty($this->restartCommand[1]) || count($this->restartCommand[1]) == 0) {
            throw new InvalidArgumentException('Please set a legal command');
        }
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
    private function setWatch($dir)
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
                    if($mic-$this->lastStartTime>$this->restartInterval){
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
    private function restart()
    {
        $this->swooleServerPid && \swoole_process::kill($this->swooleServerPid);

        if ($this->showDetail) {
            echo 'Server restart...'.PHP_EOL;
        }
        //开一个进程来进行重启
        $process = new \swoole_process(function(\swoole_process $worker){

            $worker->exec($this->restartCommand[0],$this->restartCommand[1]);

        }, false, false);

        $this->swooleServerPid=$process->start();

        echo 'Restart successfully,pid:'.$this->swooleServerPid.PHP_EOL;

    }

    private function microtime()
    {
        $mic=microtime(true);

        $mic=substr($mic,2);
        return $mic*1000;
    }


    /**
     * 依赖检测
     */
    private function checkDepends()
    {
        if(!extension_loaded('inotify')){
            throw new DependsException('php inotify extension is required');
        }

        if (!extension_loaded('swoole')) {
            throw new DependsException('php swoole extension is required');
        }
    }


    /**
     * 开始监听
     * @throws DependsException
     */
    public function listen()
    {
        //监测依赖
        $this->checkDepends();

        $this->fd=inotify_init();

        //开始扫描目录递归监听
        $this->scanDirRecursion($this->rootDir);

        //监听单个目录
        //$this->setWatch($this->rootDir);

        \swoole_process::signal(SIGCHLD, function($sig) {
            //必须为false，非阻塞模式
            while($ret =  \swoole_process::wait(false)) {
                if($ret['pid']==$this->swooleServerPid)$this->swooleServerPid=0;
                if($this->showDetail){
                    echo 'Process[pid='.$ret['pid'].']has been recycled'.PHP_EOL;
                }
            }
        });
    }




}