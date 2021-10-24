<?php

require_once 'vendor/autoload.php';

$server = new \PHPNet\Server('tcp://127.0.0.1:1234');
// 监听
$server->listen();
// 等待客户端连接
$server->accept();
