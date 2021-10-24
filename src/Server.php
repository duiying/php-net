<?php

namespace PHPNet;

class Server
{
    public $localSocket;
    public $serverSocket;
    public $connections = [];

    public function __construct($localSocket)
    {
        $this->localSocket = $localSocket;
    }

    public function listen()
    {
        // TCP
        $flag = STREAM_SERVER_BIND | STREAM_SERVER_LISTEN;

        $option['socket']['backlog'] = 10;
        $context = stream_context_create($option);

        $this->serverSocket = stream_socket_server($this->localSocket, $errorCode, $errorMsg, $flag, $context);
        if ($this->serverSocket === false) {
            echo '服务启动失败' . PHP_EOL;
            exit(0);
        }
    }

    public function accept()
    {
        $connectSocket = stream_socket_accept($this->serverSocket, -1);
        if ($connectSocket !== false) {
            echo '有客户端连接了' . PHP_EOL;
            $this->connections[(int)$connectSocket] = $connectSocket;
        }
    }
}