<?php

require_once 'vendor/autoload.php';

$server = new \PHPNet\Server('tcp://127.0.0.1:1234');

// 注册 connect 回调
$server->on('connect', function (\PHPNet\Server $server, \PHPNet\TcpConnection $tcpConnection) {
    $tcpConnection->executeConnect();
});

// 注册 receive 回调
$server->on('receive', function (\PHPNet\Server $server, \PHPNet\TcpConnection $tcpConnection) {
    $tcpConnection->recvFromSocket();
});

// 监听
$server->listen();
// IO 多路复用，处理多个客户端连接
$server->eventLoop();
