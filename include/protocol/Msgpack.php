<?php
namespace DF\Protocol;

class Msgpack
{
    public function serialize($data)
    {
        $tmp = msgpack_pack($data);
        return pack('N', strlen($tmp)).$tmp;
    }

    public function unserialize($data)
    {
        $ret = array();
        while($len = strlen($data)){
            $arr = unpack('N', substr($data, 0, 4));
            $strlen = array_pop($arr);
            $ret[] = msgpack_unpack(substr($data, 4, $strlen));
            if(4 + $strlen == $len){
                break;
            }
            $data = substr($data, 4 + $strlen);
        }
        return $ret;
    }
    
    public function encode(...$data)
    {
        return $this->serialize($data);
    }
}