<?php

require_once 'vendor/autoload.php';

// 当需要使用 TCP 底层的字节流协议时，使用下面的协议参数
// $server = new \PHPNet\Server('tcp://127.0.0.1:1234');
// Stream 字节流协议
$server = new \PHPNet\Server('stream://127.0.0.1:1234');

// 注册 connect 回调
$server->on('connect', function (\PHPNet\Server $server, \PHPNet\TcpConnection $tcpConnection) {
    $tcpConnection->executeConnect();
});

// 注册 receive 回调
$server->on('receive', function (\PHPNet\Server $server, \PHPNet\TcpConnection $tcpConnection) {
    $tcpConnection->receive();
});

// 注册 close 回调
$server->on('close', function (\PHPNet\Server $server, \PHPNet\TcpConnection $tcpConnection) {
    $tcpConnection->close();
});

// 启动服务
$server->start();
