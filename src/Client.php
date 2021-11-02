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

    // 发送缓冲区大小
    public $sendBufferSize = 1024 * 100;
    // 发送缓冲区
    public $sendBuffer = '';
    // 已发送长度
    public $sendLen = 0;
    // 发送缓冲区满次数
    public $sendBufferFull = 0;

    // 写入到发送缓冲区的次数统计
    public $writeToBufferStat = 0;
    // 写入到 socket 的次数统计
    public $writeToSocketStat = 0;

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
            $this->executeEventCallback('error', [$errorCode, $errorMsg]);
            exit(0);
        }
        $this->executeEventCallback('connect');
        // $this->eventLoop();
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
        //while (1) {
        $readSocketList = [$this->clientSocket];
        $writeSocketList = [$this->clientSocket];
        $exceptionSocketList = [$this->clientSocket];

        $changedSocketCount = stream_select($readSocketList, $writeSocketList, $exceptionSocketList, 0);
        if ($changedSocketCount === false) {
            echo '发生错误了' . PHP_EOL;
            // break;
            return false;
        }

        // 如果有了可读 socket
        if (!empty($readSocketList)) {
            $this->recvFromSocket();
        }

        // 如果有了可写 socket
        if (!empty($writeSocketList)) {
            $this->writeToSocket();
        }

        return true;
        //}
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
                $this->executeEventCallback('close');
                return;
            }
        } // 如果读缓冲区有数据
        else {
            // 把接收到的数据放在接收缓冲区中
            $this->recvBuffer .= $data;
            $this->receivedLen += strlen($data);
        }

        if ($this->receivedLen > 0) {
            $this->handleMsg();
        }
    }

    /**
     * 处理消息
     */
    public function handleMsg()
    {
        while ($this->protocol->checkLen($this->recvBuffer)) {
            $length = $this->protocol->msgLen($this->recvBuffer);

            // 截取一条消息
            $msg = substr($this->recvBuffer, 0, $length);

            // 从接收缓冲区删除这条消息
            $this->recvBuffer = substr($this->recvBuffer, $length);
            $this->receivedLen -= $length;

            $msg = $this->protocol->decode($msg);

            // 执行 receive 回调
            $this->executeEventCallback('receive', [$msg]);
        }
    }

    /**
     * 发送缓冲区满了
     */
    public function handleSendBufferFull()
    {
        $this->sendBufferFull++;
        echo sprintf('发送缓冲区满了' . PHP_EOL);
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
     * 写入发送缓冲区
     *
     * @param $data
     */
    public function writeToBuffer($data)
    {
        $bin = $this->protocol->encode($data);
        $writeData = $bin['packed_data'];
        $len = $bin['length'];

        if ($this->sendLen + $len > $this->sendBufferSize) {
            $this->handleSendBufferFull();
        }

        $this->writeToBufferStat++;

        $this->sendLen += $len;
        $this->sendBuffer .= $writeData;
    }

    /**
     * 向客户端 socket 写数据
     */
    public function writeToSocket()
    {
        if ($this->sendLen > 0) {
            $writeLen = fwrite($this->clientSocket, $this->sendBuffer, $this->sendLen);

            $this->writeToSocketStat++;

            // 全部写入成功
            if ($writeLen === $this->sendLen) {
                $this->sendBuffer = '';
                $this->sendLen = 0;
            }
            // 部分写入成功
            elseif ($writeLen > 0) {
                $this->sendBuffer = substr($this->sendBuffer, $writeLen);
                $this->sendLen -= $writeLen;
            }
            // 写入失败
            else {
                $this->executeEventCallback('close');
            }
        }
    }
}