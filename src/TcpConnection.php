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
    // 读缓冲区大小（100KB）
    public $readBufferSize = 1024 * 100;
    // 接收缓冲区大小（100KB）
    public $recvBufferSize = 1024 * 100;
    // 当前连接目前接收到的字节数大小
    public $receivedLen = 0;
    // 当前连接接收的字节数是否超出缓冲区
    public $recvBufferFull = 0;
    // 接收缓冲区
    public $recvBuffer = '';

    public function __construct($connectSocket, $clientIp, $server)
    {
        $this->connectSocket = $connectSocket;
        $this->clientIp = $clientIp;
        $this->server = $server;
    }

    public function getConnectSocket()
    {
        return $this->connectSocket;
    }

    /**
     * 处理连接
     */
    public function executeConnect()
    {
        echo sprintf('客户端 %d 连接了' . PHP_EOL, (int)$this->connectSocket);
        $this->writeToSocket('pong');
    }

    /**
     * 从客户端 socket 读取数据
     */
    public function receive()
    {
        // 如果超出了接收缓冲区大小
        if ($this->receivedLen > $this->recvBufferSize) {
            $this->recvBufferFull++;
        }

        $data = fread($this->connectSocket, $this->readBufferSize);

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
            while ($this->server->protocol->checkLen($this->recvBuffer)) {
                $length = $this->server->protocol->msgLen($this->recvBuffer);

                // 截取一条消息
                $msg = substr($this->recvBuffer, 0, $length);

                $this->recvBuffer = substr($this->recvBuffer, $length);
                $this->receivedLen -= $length;

                $msg = $this->server->protocol->decode($msg);

                echo sprintf('服务端收到了客户端 %d 一条消息 %s' . PHP_EOL, (int)$this->connectSocket, $msg);
            }
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
    }

    /**
     * 向客户端 socket 写数据
     *
     * @param $data
     */
    public function writeToSocket($data)
    {
        $bin = $this->server->protocol->encode($data);
        $writeLen = fwrite($this->connectSocket, $bin['packed_data'], $bin['length']);
        echo sprintf('写了 %d 个字符' . PHP_EOL, $writeLen);
    }
}