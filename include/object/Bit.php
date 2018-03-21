<?php
namespace DF\Object;

class Bit
{
    const BIT_SIZE = 8;
    private $str = '';
    private $arr = array();
    
    public function __construct($mixed = '')
    {
        if(is_array($mixed)){
            $this->arr = $mixed;
        }else{
            if(empty($mixed)){
                $this->str = pack('C', 0);
            }else{
                $this->str = (string)$mixed;
            }
        }
    }

    public function writeBit($row, $pos, $val = 1)
    {
        if(!isset($this->arr[$row])){
            $this->arr[$row] = '';
        }
        if($pos < 1){
            return false;
        }
        $pos--;
        $word = intval($pos / self::BIT_SIZE);
        $index = $pos % self::BIT_SIZE;
        while(!isset($this->str[$word])){
            $this->str .= pack('C', 0);
        }
        $old = $this->arr[$row][$word];
        if($val){
            $new = ord($old) | (1 << $index);
        }else{
            $new = ord($old) & ~(1 << $index);
        }
        $this->arr[$row][$word] = pack('C', $new);
    }
    
    public function readBit($row, $pos)
    {
        if(!isset($this->arr[$row])){
            $this->arr[$row] = '';
        }
        if($pos < 1){
            return false;
        }
        $pos--;
        $word = intval($pos / self::BIT_SIZE);
        $index = $pos % self::BIT_SIZE;
        while(!isset($this->str[$word])){
            $this->str .= pack('C', 0);
        }
        $old = $this->arr[$row][$word];
        return (ord($old) >> $index) & 1;
    }
    
    public function setBit($pos, $val = 1)
    {
        if($pos < 1){
            return false;
        }
        $pos--;
        $word = intval($pos / self::BIT_SIZE);
        $index = $pos % self::BIT_SIZE;
        while(!isset($this->str[$word])){
            $this->str .= pack('C', 0);
        }
        $old = $this->str[$word];
        if($val){
            $new = ord($old) | (1 << $index);
        }else{
            $new = ord($old) & ~(1 << $index);
        }
        $this->str[$word] = pack('C', $new);
    }
    
    public function getBit($pos)
    {
        if($pos < 1){
            return false;
        }
        $pos--;
        $word = intval($pos / self::BIT_SIZE);
        $index = $pos % self::BIT_SIZE;
        while(!isset($this->str[$word])){
            $this->str .= pack('C', 0);
        }
        $old = $this->str[$word];
        return (ord($old) >> $index) & 1;
    }
    
    public function getString()
    {
        return $this->str;
    }
    
    public function getArray()
    {
        return $this->arr;
    }
}