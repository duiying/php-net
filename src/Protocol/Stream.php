<?php

namespace PHPNet\Protocol;

/**
 * Stream 字节流协议
 * 消息的组成：数据总长度（4 字节）+ 数据命令（2 字节）+ 数据载荷
 *
 * @package PHPNet\Protocol
 */
class Stream implements Protocol
{
    /**
     * 检查数据是否完整
     *
     * @param $data
     * @return bool
     */
    public function checkLen($data)
    {
        if (strlen($data) <= 4) {
            return false;
        }
        $totalLenInfo = unpack('Nlength', $data);
        if (strlen($data) < $totalLenInfo['length']) {
            return false;
        }
        return true;
    }

    /**
     * 数据编码（由「消息的数据包总长度（4 字节）+ 消息的命令（2 字节）+ 数据载荷」构成）
     *
     * @param string $data
     * @return array
     */
    public function encode($data = '')
    {
        $totalLen = strlen($data) + 6;
        // N：4 字节，代表一条消息的总长度；n：2 字节，代表消息的命令（暂时不用）；
        $bin = pack('Nn', $totalLen, '1') . $data;
        return ['length' => $totalLen, 'packed_data' => $bin];
    }

    /**
     * 数据解码
     *
     * @param string $data
     * @return false|string
     */
    public function decode($data = '')
    {
        $cmd = substr($data, 4, 2);
        $data = substr($data, 6);
        return $data;
    }

    /**
     * 返回一条消息的总长度
     *
     * @param string $data
     * @return mixed
     */
    public function msgLen($data = '')
    {
        $lengthInfo = unpack('Nlength', $data);
        return $lengthInfo['length'];
    }
}