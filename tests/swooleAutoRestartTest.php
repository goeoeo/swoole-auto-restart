<?php
/**
 * Auth:chenyu.
 * Mail:phpdi@sina.com
 * Date: 18-12-14
 * Desc:
 */
namespace Phpdic\SwooleAutoRestart\Tests;

use Phpdic\SwooleAutoRestart\Exceptions\InvalidArgumentException;
use Phpdic\SwooleAutoRestart\swooleAutoRestart;
use PHPUnit\Framework\TestCase;

class swooleAutoRestartTest extends TestCase
{


    public function testListenWithInvalidArgumentExceptionForRootDirectory()
    {

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Please set a root directory');


        new swooleAutoRestart('testsss','php artisan websocket:start');
    }

    public function testListenWithInvalidArgumentExceptionForCommand()
    {

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Please set a command');


        new swooleAutoRestart(__DIR__,'');
    }

    public function testListenWithInvalidArgumentExceptionForCommandLegal()
    {

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Please set a legal command');


        new swooleAutoRestart(__DIR__,'php');
    }

}