<?php
namespace DF\Base;

class Redis
{
    private static $pool = array();
    private $name = '';
    private $client = null;
    
    private function __construct($name, $client)
    {
        $this->name = $name;
        $this->client = $client;
    }

    public static function getInstance($name)
    {
        if(isset(self::$pool[$name])){
            return self::$pool[$name];
        }
        $client = self::connect($name);
        if(empty($client)){
            return false;
        }
        self::$pool[$name] = new self($name, $client);
        return self::$pool[$name];
    }
    
    public static function connect($name)
    {
        $config = Config::getConfig(Config::CONFIG_REDIS);
        if(!isset($config[$name])){
            return false;
        }
        $timeout = isset($config[$name]['timeout']) ? $config[$name]['timeout'] : 0;
        $redis = new \Redis();
        $redis->pconnect($config[$name]['host'], $config[$name]['port'], $timeout, $name);
        if(isset($config[$name]['password'])){
            $ret = $redis->auth($config[$name]['password']);
            if(!$ret){
                return false;
            }
        }
        return $redis;
    }
    
    private function evalsha()
    {
        $args = func_get_args();
        $script = $args[0];
        $sha1 = sha1($script);
        if(empty($this->client)){
            return null;
        }
        try{
            $args[0] = $sha1;
            $ret = call_user_func_array(array($this->client, 'evalsha'), $args);
            return $ret;
        } catch (Exception $ex) {
            
        }
        return null;
    }
    
    public function __call($name, $arguments)
    {
        $client = $this->client;
        if(empty($client)){
            return null;
        }
        try {
            if($name == 'eval'){
                $ret = call_user_func_array(array($this, 'evalsha'), $arguments);
                return $ret;
            }
            $ret = call_user_func_array(array($client, $name), $arguments);
            return $ret;
        } catch (Exception $exc) {
            return false;
        }
    }
}

