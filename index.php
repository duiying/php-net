<?php

require_once 'vendor/autoload.php';

$server = new \PHPNet\Server('tcp://127.0.0.1:1234');
// 监听
$server->listen();
// IO 多路复用，处理多个客户端连接
$server->eventLoop();
