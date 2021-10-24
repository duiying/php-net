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

    /**
     * 监听
     */
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

        echo sprintf('listen on %s' . PHP_EOL, $this->localSocket);
    }

    /**
     * 处理客户端连接
     */
    public function accept()
    {
        $connectSocket = stream_socket_accept($this->serverSocket, -1);
        if ($connectSocket !== false) {
            echo '有客户端连接了' . PHP_EOL;
            $this->connections[(int)$connectSocket] = $connectSocket;
        }
    }

    public function eventLoop()
    {
        while (1) {
            // 监听 socket 只会有连接事件，所以只放入读监听数组中
            // 因为调用 select 的时候，会修改 $readSocketList、$writeSocketList、$exceptionSocketList，所以再次调用 select 的时候，需要重新赋值 $readSocketList、$writeSocketList、$exceptionSocketList
            $readSocketList         = [$this->serverSocket];
            $writeSocketList        = [];
            $exceptionSocketList    = [];

            // 将连接 socket 放入读、写监听数组中
            if (!empty($this->connections)) {
                foreach ($this->connections as $k => $connectSocket) {
                    $readSocketList[]   = $connectSocket;
                    $writeSocketList[]  = $connectSocket;
                }
            }

            // 当 select 返回时，内核会修改 readfds、writefds、exceptfds 参数并通知应用程序哪些文件描述符上有事件发生了，同时返回就绪（读、写、异常）的文件描述符总数
            // select API：https://man7.org/linux/man-pages/man2/select.2.html
            $changedSocketCount = stream_select($readSocketList, $writeSocketList, $exceptionSocketList, 0, 200000);
            if ($changedSocketCount === false) {
                echo '发生错误了' . PHP_EOL;
                break;
            }

            // 如果有了可读 socket
            if (!empty($readSocketList)) {
                foreach ($readSocketList as $k => $readSocket) {
                    // 如果是监听 socket 可读，说明是有了客户端连接
                    if ($readSocket === $this->serverSocket) {
                        $this->accept();
                    }
                    // 如果不是监听 socket 可读，说明是客户端发来了数据
                    else {
                        $data = fread($readSocket, 1024);
                        if (!empty($data)) {
                            echo sprintf('收到了客户端 %d 发来的数据：%s' . PHP_EOL, (int)$readSocket, $data);
                            fwrite($readSocket, 'pong');
                        }
                    }
                }
            }
        }
    }
}