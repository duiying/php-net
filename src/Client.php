<?php

namespace PHPNet;

use PHPNet\Protocol\Stream;

class Client
{
    public $clientSocket;
    // 回调
    public $events = [];
    // 读缓冲区大小（100KB）
    public $readBufferSize = 1024 * 100;
    // 当前连接目前接收到的字节数大小
    public $receivedLen = 0;
    // 接收缓冲区（这是一个缓冲区，可以接收多条消息，数据之间是黏在一起的，像水流一样）
    public $recvBuffer = '';
    // 使用到的协议
    public $protocol;
    public $localSocket;

    public function __construct($localSocket)
    {
        $this->localSocket = $localSocket;
        $this->protocol = new Stream();
    }

    /**
     * 客户端启动
     */
    public function start()
    {
        $this->clientSocket = stream_socket_client($this->localSocket, $errorCode, $errorMsg);
        if ($this->clientSocket === false) {
            $this->executeEventCallback('error', [$this, $errorCode, $errorMsg]);
            exit(0);
        }
        $this->executeEventCallback('connect', [$this]);
        $this->eventLoop();
    }

    /**
     * 增加回调
     *
     * @param $event
     * @param $func
     */
    public function on($event, $func)
    {
        $this->events[$event] = $func;
    }

    /**
     * 执行回调
     *
     * @param $event
     * @param array $args
     */
    public function executeEventCallback($event, $args = [])
    {
        if (isset($this->events[$event]) && is_callable($this->events[$event])) {
            $this->events[$event]($this, ...$args);
        }
    }

    /**
     * 错误处理
     *
     * @param $errorCode
     * @param $errorMsg
     */
    public function handleError($errorCode, $errorMsg)
    {
        echo sprintf('发生错误了，errorCode：%d errorMsg：%s' . PHP_EOL, $errorCode, $errorMsg);
    }

    /**
     * 客户端事件循环
     */
    public function eventLoop()
    {
        while (1) {
            $readSocketList         = [$this->clientSocket];
            $writeSocketList        = [$this->clientSocket];
            $exceptionSocketList    = [$this->clientSocket];
            if ($this->clientSocket === false) {
                echo '服务端关闭了' . PHP_EOL;
                break;
            }

            $changedSocketCount = stream_select($readSocketList, $writeSocketList, $exceptionSocketList, 0, 200000);
            if ($changedSocketCount === false) {
                echo '发生错误了' . PHP_EOL;
                break;
            }

            // 如果有了可读 socket
            if (!empty($readSocketList)) {
                $this->recvFromSocket();
            }
        }
    }

    /**
     * 从 socket 读取数据
     */
    public function recvFromSocket()
    {
        $data = fread($this->clientSocket, $this->readBufferSize);

        // 如果读缓冲区无数据
        if ($data === '' || $data === false) {
            if (feof($this->clientSocket)) {
                // 执行 close 回调
                $this->executeEventCallback('close', [$this]);
                return;
            }
        }
        // 如果读缓冲区有数据
        else {
            // 把接收到的数据放在接收缓冲区中
            $this->recvBuffer .= $data;
            $this->receivedLen += strlen($data);
        }

        if ($this->receivedLen > 0) {
            // 执行 receive 回调
            $this->executeEventCallback('receive', [$this]);
        }
    }

    /**
     * 客户端收到数据时执行
     */
    public function receive()
    {
        while ($this->protocol->checkLen($this->recvBuffer)) {
            $length = $this->protocol->msgLen($this->recvBuffer);

            // 截取一条消息
            $msg = substr($this->recvBuffer, 0, $length);

            $this->recvBuffer = substr($this->recvBuffer, $length);
            $this->receivedLen -= $length;

            $msg = $this->protocol->decode($msg);

            echo sprintf('客户端 %d 收到了一条消息 %s' . PHP_EOL, (int)$this->clientSocket, $msg);

            $this->writeToSocket('hello');
        }
    }

    /**
     * 客户端断开连接时执行
     */
    public function close()
    {
        echo sprintf('服务端 %d 断开连接了' . PHP_EOL, (int)$this->clientSocket);
        fclose($this->clientSocket);
    }

    /**
     * 向客户端 socket 写数据
     *
     * @param $data
     */
    public function writeToSocket($data)
    {
        $bin = $this->protocol->encode($data);
        $writeLen = fwrite($this->clientSocket, $bin['packed_data'], $bin['length']);
        echo sprintf('客户端 %d 写了 %d 个字符' . PHP_EOL, (int)$this->clientSocket, $writeLen);
    }
}