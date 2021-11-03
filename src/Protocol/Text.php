<?php

namespace PHPNet\Protocol;

/**
 * Text 流协议（以换行符分隔多条消息）
 *
 * @package PHPNet\Protocol
 */
class Text implements Protocol
{
    public function checkLen($data)
    {
        return strlen($data) > 0;
    }

    public function encode($data = '')
    {
        $data = $data . PHP_EOL;
        return ['length' => strlen($data), 'packed_data' => $data];
    }

    public function decode($data = '')
    {
        return rtrim($data, PHP_EOL);
    }

    public function msgLen($data = '')
    {
        return strpos($data, PHP_EOL) + 1;
    }
}