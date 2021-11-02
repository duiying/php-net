<?php

namespace PHPNet;

use PHPNet\Protocol\Stream;

class Server
{
    public $localSocket;
    public $serverSocket;
    public $connections = [];

    // 统计客户端连接数量
    public $clientConnectCountStat = 0;
    // 统计每秒执行 recv/fread 调用次数
    public $receiveCountStat = 0;
    // 统计每秒接收到的消息数
    public $msgCountStat = 0;

    // 时间
    public $time = 0;

    // 回调
    public $events = [];
    // 协议
    public $protocol = null;
    // 协议对应的类
    public $protocolClassMap = [
        'stream'    => 'PHPNet\Protocol\Stream',
        'text'      => '',
        'ws'        => '',
        'http'      => '',
        'mqtt'      => '',
    ];

    public function __construct($localSocket)
    {
        list($protocol, $ip, $port) = explode(':', $localSocket);
        if (isset($this->protocolClassMap[$protocol])) {
            $this->protocol = new $this->protocolClassMap[$protocol]();
        }
        $this->localSocket = sprintf('tcp:%s:%s', $ip, $port);
        $this->time = time();
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
     * 启动服务
     */
    public function start()
    {
        // 监听
        $this->listen();
        // IO 多路复用，处理多个客户端连接
        $this->eventLoop();
    }

    /**
     * 监听
     */
    public function listen()
    {
        // TCP
        $flag = STREAM_SERVER_BIND | STREAM_SERVER_LISTEN;

        $option['socket']['backlog'] = 1024;
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
     * 每秒打印一次统计信息
     */
    public function printStatisticsInfo()
    {
        $now = time();

        $diffTime = $now - $this->time;

        // 如果超过了 1 秒
        if ($diffTime >= 1) {
            echo sprintf('diffTime：%d socket：%d clientCount：%d recvCount：%d msgCount：%d' . PHP_EOL,
            $diffTime, (int)$this->serverSocket, $this->clientConnectCountStat, $this->receiveCountStat, $this->msgCountStat);

            $this->msgCountStat = 0;
            $this->receiveCountStat = 0;
            $this->time = $now;
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

            $this->printStatisticsInfo();

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

            /**
             * 文件描述符什么时候产生就绪事件？
             *
             * 可读情况：
             * 1、 socket 内核接收缓冲区的字节数 >= SO_RCVLOWAT 水位标记时，执行读操作时返回的字节数 > 0
             * 2、 对端关闭时，此时的读操作返回 0
             * 3、 监听 socket 有新客户端连接时
             * 4、 socket 上有为处理的错误，可使用 getsockopt 来读取和清除错误
             *
             * 可写情况：
             * 1、 socket 内核发送缓冲区的可用字节数 >= 低水位标记 SO_SNDLOWAT 时，执行写操作，返回的写字节数 > 0
             * 2、 对端关闭时，写操作会触发 SIGPIPE 中断信号
             * 3、 socket 有未处理的错误时
             */

            // 如果有了可读 socket
            if (!empty($readSocketList)) {
                foreach ($readSocketList as $k => $readSocket) {
                    // 如果是监听 socket 可读，说明是有了客户端连接
                    if ($readSocket === $this->serverSocket) {
                        $this->accept();
                    }
                    // 如果不是监听 socket 可读，说明是客户端发来了数据
                    else {
                        if (isset($this->connections[(int)$readSocket])) {
                            /** @var TcpConnection $tcpConnection */
                            $tcpConnection = $this->connections[(int)$readSocket];
                            $tcpConnection->recvFromSocket();
                        }
                    }
                }
            }

            // 如果有了可写 socket
            if (!empty($writeSocketList)) {
                foreach ($writeSocketList as $k => $writeSocket) {
                    /** @var TcpConnection $tcpConnection */
                    $tcpConnection = $this->connections[(int)$readSocket];
                    $tcpConnection->writeToSocket();
                }
            }
        }
    }
}