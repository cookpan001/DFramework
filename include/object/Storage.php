<?php
namespace DF\Object;

class Storage
{
    private $obj = null;
    
    public function __construct() {
        $this->obj = array();
    }
    
    public function set($key, $value)
    {
        $this->obj[$key] = $value;
        return $this;
    }
    
    public function get($key)
    {
        if(isset($this->obj[$key])){
            return $this->obj[$key];
        }
        return null;
    }
}