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
    // 读缓冲区大小
    public $readBufferSize = 1024;
    // 接收缓冲区大小（100KB）
    public $recvBufferSize = 1024 * 100;
    // 当前连接目前接收到的字节数大小
    public $recvLen = 0;
    // 当前连接接收的字节数是否超出缓冲区
    public $recvBufferFull = 0;

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
        $data = fread($this->connectSocket,$this->readBufferSize);

        // 如果读缓冲区有数据
        if (!empty($data)) {
            echo sprintf('有客户端发送数据了 %s' . PHP_EOL, $data);
            $this->writeToSocket('pong');
            return;
        }

        // 如果读缓冲区无数据
        if ($data === '' || $data === false) {
            if (feof($this->connectSocket)) {
                // 执行 close 回调
                $this->server->executeEventCallback('close', [$this]);
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
        $len = strlen($data);
        $writeLen = fwrite($this->connectSocket, $data, $len);
        echo sprintf('写了 %d 个字符' . PHP_EOL, $len);
    }
}