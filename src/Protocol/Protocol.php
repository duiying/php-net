<?php

namespace PHPNet\Protocol;

/**
 * 协议接口
 *
 * @package PHPNet\Protocol
 */
interface Protocol
{
    public function checkLen($data);
    public function encode($data = '');
    public function decode($data = '');
    public function msgLen($data = '');
}