<?php

use PHPNet\Client;

require_once 'vendor/autoload.php';

// 启动多少个客户端连接
$clientNum = $argv['1'] ?? 1;

$clientList = [];

for ($i = 0; $i < $clientNum; $i++) {
    $clientList[] = $client = new Client('tcp://127.0.0.1:1234');

    // 注册 connect 回调
    $client->on('connect', function (Client $client) {
        echo sprintf('client %d connect success' . PHP_EOL, (int)$client->clientSocket);
    });

    // 注册 error 回调
    $client->on('error', function (Client $client, $errorCode, $errorMsg) {
        $client->handleError($errorCode, $errorMsg);
    });

    // 注册 close 回调
    $client->on('close', function (Client $client) {
        $client->close();
    });

    // 注册 receive 回调
    $client->on('receive', function (Client $client, $msg) {
    });

    $client->start();
}

while (1) {
    foreach ($clientList as $k => $v) {
        /** @var Client $client */
        $client = $v;

        // 写入缓冲区
        for ($i = 0; $i < 5; $i++) {
            $client->writeToBuffer(sprintf('client,time:%s', date('Y-m-d H:i:s')));
        }

        $client->eventLoop();
    }
}




