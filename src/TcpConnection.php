<?php

namespace PHPNet;

class TcpConnection
{
    // 客户端连接 socket
    public $connectSocket;
    // 客户端连接 IP
    public $clientIp;
    // 主服务
    public $server;

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
        echo '有客户端连接了' . PHP_EOL;
    }

    /**
     * 从客户端 socket 读取数据
     */
    public function recvFromSocket()
    {
        $data = fread($this->connectSocket, 1024);
        if (!empty($data)) {
            echo sprintf('有客户端发送数据了 %s' . PHP_EOL, $data);
        }
        $this->writeToSocket('pong');
    }

    /**
     * 向客户端 socket 写数据
     *
     * @param $data
     */
    public function writeToSocket($data)
    {
        $len = strlen($data);
        $writeLen = fwrite($this->connectSocket, $data, $len);
        echo sprintf('写了 %d 个字符' . PHP_EOL, $len);
    }
}