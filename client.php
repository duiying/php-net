<?php

require_once 'vendor/autoload.php';

$clientNum = $argv[1] ?? 1;
$clientList = [];

for ($i = 0; $i < $clientNum; $i++) {
    $client = new \PHPNet\Client('tcp://127.0.0.1:1234');

    // 注册 connect 回调
    $client->on('connect', function (\PHPNet\Client $client) {
        $client->writeToSocket('ping');
    });

    // 注册 error 回调
    $client->on('error', function (\PHPNet\Client $client, $errorCode, $errorMsg) {
        $client->handleError($errorCode, $errorMsg);
    });

    // 注册 close 回调
    $client->on('close', function (\PHPNet\Client $client) {
        $client->close();
    });

    // 注册 receive 回调
    $client->on('receive', function (\PHPNet\Client $client) {
        $client->receive();
    });

    $clientList[] = $client;
}

foreach ($clientList as $k => $v) {
    $v->start();
}