<?php

namespace PHPNet;

class Server
{
    public $localSocket;
    public $serverSocket;
    public $connections = [];
    // 回调
    public $events = [];

    public function __construct($localSocket)
    {
        $this->localSocket = $localSocket;
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

        echo sprintf('server pid %d listen on %s' . PHP_EOL, posix_getpid(), $this->localSocket);
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
     * 处理客户端连接
     */
    public function accept()
    {
        $connectSocket = stream_socket_accept($this->serverSocket, -1, $peerName);
        if ($connectSocket !== false) {
            $tcpConnection = new TcpConnection($connectSocket, $peerName, $this);
            $this->connections[(int)$connectSocket] = $tcpConnection;

            // 执行 connect 回调
            $this->executeEventCallback('connect', [$tcpConnection]);
        }
    }

    /**
     * 事件循环
     */
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
                foreach ($this->connections as $k => $v) {
                    /** @var TcpConnection $tcpConnection */
                    $tcpConnection = $v;
                    $connectSocket = $tcpConnection->getConnectSocket();

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
                        /** @var TcpConnection $tcpConnection */
                        $tcpConnection = $this->connections[(int)$readSocket];
                        $tcpConnection->recvFromSocket();
                    }
                }
            }
        }
    }
}