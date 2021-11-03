<?php

/**
 * 检查服务端心跳机制用
 */

$client = socket_create(AF_INET, SOCK_STREAM, 0);

if (socket_connect($client, '127.0.0.1', 1234)) {
    socket_write($client, 'ping', 4);
    echo "从服务端收到了数据：" . socket_read($client, 1024) . PHP_EOL;
}

sleep(8);
socket_write($client, 'ping', 4);
echo "从服务端收到了数据：" . socket_read($client, 1024) . PHP_EOL;
sleep(8);
socket_close($client);