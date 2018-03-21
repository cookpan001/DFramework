<?php

namespace DF\Object;

class Storage
{
    private static $instance = null;
    
    private $obj = null;
    private $expire = null;

    private function __construct()
    {
        $this->obj = array();
    }
    
    public static function getInstance()
    {
        if(is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function set($key, $value, $expire = 0)
    {
        $this->obj[$key] = $value;
        if(defined('IN_SWOOLE') && IN_SWOOLE && $expire > 0){
            $this->expire[$key] = time() + 300;
        }
        return $this;
    }

    public function setSub($key, $subKey, $value, $expire = 0)
    {
        $this->obj[$key][$subKey] = $value;
        if(defined('IN_SWOOLE') && IN_SWOOLE && $expire > 0){
            $this->expire[$key] = time() + 300;
        }
        return $this;
    }

    public function get($key, $subKey = null)
    {
        if (isset($this->obj[$key])) {
            if(isset($this->expire[$key]) && $this->expire[$key] < tim()){
                unset($this->obj[$key]);
                unset($this->expire[$key]);
                return null;
            }
            if(is_null($subKey)){
                return $this->obj[$key];
            }
            return isset($this->obj[$key][$subKey]) ? $this->obj[$key][$subKey] : null;
        }
        return null;
    }

}
