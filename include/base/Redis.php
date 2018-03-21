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
        $config = Config::getRedis();
        if(!isset($config[$name])){
            return false;
        }
        $timeout = isset($config[$name]['timeout']) ? $config[$name]['timeout'] : 0;
        $password = isset($config[$name]['password']) ? $config[$name]['password'] : 0;
        if(extension_loaded('redis')){
            $redis = new \Redis();
            $redis->pconnect($config[$name]['host'], $config[$name]['port'], $timeout, $name);
            if($password){
                $ret = $redis->auth($config[$name]['password']);
                if(!$ret){
                    return false;
                }
            }
        }else if(class_exists('\Predis\Client')){
            $options = array();
            if($password){
                $options = [
                    'parameters' => [
                        'password' => $password,
                    ],
                ];
            }
            $redis = new \Predis\Client("tcp://{$config[$name]['host']}:{$config[$name]['port']}", $options);
        }else{
            $redis = new \DF\Sys\RedisClient($config[$name]['host'], $config[$name]['port'], $timeout, $password);
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
        } catch (\Exception $ex) {
            if($ex->getMessage() == 'NOSCRIPT No matching script. Please use EVAL.'){
                $args[0] = $script;
                return call_user_func_array(array($this->client, 'eval'), $args);
            }
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
            $config = Config::getRedis();
            $prefix = isset($config[$this->name]['prefix']) ? $config[$this->name]['prefix'] : '';
            $t1 = microtime(true);
            $ret = call_user_func_array(array($client, $name), $arguments);
            $t2 = microtime(true);
            \DF\Base\Log::redis($this->name, $name, ($t2 - $t1) * 1000, ...$arguments );
            return $ret;
        } catch (\Exception $exc) {
            return false;
        }
    }
}

