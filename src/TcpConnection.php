<?php

namespace PHPNet;

class TcpConnection
{
    // 客户端连接 socket
    public $connectSocket;
    // 客户端连接 IP
    public $clientIp;
    /** @var Server $server 主服务 */
    public $server;

    // 读缓冲区大小（1KB）
    public $readBufferSize = 1024 * 100;

    // 接收缓冲区大小（100KB）
    public $recvBufferSize = 1024 * 100;
    // 当前连接目前接收到的字节数大小
    public $receivedLen = 0;
    // 当前连接接收的字节数是否超出缓冲区
    public $recvBufferFull = 0;
    // 接收缓冲区
    public $recvBuffer = '';

    // 发送缓冲区大小
    public $sendBufferSize  = 1024 * 100;
    // 发送缓冲区
    public $sendBuffer      = '';
    // 已发送长度
    public $sendLen         = 0;
    // 发送缓冲区满次数
    public $sendBufferFull  = 0;

    // 心跳时间
    public $heartTime = 0;
    const MAX_HEART_TIME = 10;

    public function __construct($connectSocket, $clientIp, $server)
    {
        $this->connectSocket = $connectSocket;
        $this->clientIp = $clientIp;
        $this->server = $server;
        $this->heartTime = time();
    }

    /**
     * 检查心跳时间
     *
     * @return bool
     */
    public function checkHeartTime()
    {
        $now = time();
        if ($now - $this->heartTime > self::MAX_HEART_TIME) {
            echo sprintf('心跳时间已经超过 %d 秒' . PHP_EOL, self::MAX_HEART_TIME);
            return false;
        }
        return true;
    }

    /**
     * 处理连接
     */
    public function executeConnect()
    {
        $this->server->clientConnectCountStat++;
        echo sprintf('客户端 %d 连接了' . PHP_EOL, (int)$this->connectSocket);
    }

    /**
     * 接收缓冲区满了
     */
    public function handleRecvBufferFull()
    {
        $this->recvBufferFull++;
        echo sprintf('接收缓冲区满了' . PHP_EOL);
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
     * 从客户端 socket 读取数据
     */
    public function recvFromSocket()
    {
        // 如果超出了接收缓冲区大小
        if ($this->receivedLen > $this->recvBufferSize) {
            $this->handleRecvBufferFull();
        }

        $data = fread($this->connectSocket, $this->readBufferSize);
        $this->server->receiveCountStat++;

        // 如果读缓冲区无数据
        if ($data === '' || $data === false) {
            if (feof($this->connectSocket)) {
                // 执行 close 回调
                $this->server->executeEventCallback('close', [$this]);
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
            $this->handleMsg();
        }
    }

    /**
     * 处理消息
     */
    public function handleMsg()
    {
        $server = $this->server;

        // 如果协议不为空，那么走协议
        if ($server->protocol !== null) {
            while ($server->protocol->checkLen($this->recvBuffer)) {
                $length = $server->protocol->msgLen($this->recvBuffer);

                // 截取一条消息
                $msg = substr($this->recvBuffer, 0, $length);

                // 从接收缓冲区中删除这条消息
                $this->recvBuffer = substr($this->recvBuffer, $length);
                $this->receivedLen -= $length;

                $msg = $this->server->protocol->decode($msg);

                // 重置心跳时间
                $this->heartTime = time();
                $this->server->msgCountStat++;

                $this->server->executeEventCallback('receive', [$this, $msg]);
            }
        }
        // 如果协议为空，兼容 TCP 字节流协议
        else {
            $this->server->executeEventCallback('receive', [$this, $this->recvBuffer]);
            $this->recvBuffer = '';
            $this->receivedLen = 0;
            // 重置心跳时间
            $this->heartTime = time();
            $this->server->msgCountStat++;
        }
    }

    /**
     * 客户端断开连接时执行
     */
    public function close()
    {
        echo sprintf('客户端 %d 断开连接了' . PHP_EOL, (int)$this->connectSocket);

        if ($this->connectSocket !== false) {
            fclose($this->connectSocket);
        }

        if (isset($this->server->connections[(int)$this->connectSocket])) {
            unset($this->server->connections[(int)$this->connectSocket]);
        }

        $this->server->clientConnectCountStat--;
    }

    /**
     * 写入发送缓冲区
     *
     * @param $data
     */
    public function writeToBuffer($data)
    {
        $server = $this->server;

        // 如果协议不为空，那么走协议
        if ($server->protocol !== null) {
            $bin = $server->protocol->encode($data);
            $writeData = $bin['packed_data'];
            $len = $bin['length'];

            if ($this->sendLen + $len > $this->sendBufferSize) {
                $this->handleSendBufferFull();
            }
        } else {
            $len = strlen($data);
            $writeData = $data;
        }

        $this->sendLen += $len;
        $this->sendBuffer .= $writeData;
    }

    /**
     * 向客户端 socket 写数据
     */
    public function writeToSocket()
    {
        if ($this->sendLen > 0) {
            $writeLen = fwrite($this->connectSocket, $this->sendBuffer, $this->sendLen);

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
                $this->server->executeEventCallback('close', [$this]);
            }
        }
    }
}