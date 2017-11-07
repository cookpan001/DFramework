<?php
namespace DF\Base;

class PRedis
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
        $config = Base_Config::getConfig(Base_Config::CONFIG_REDIS);
        if(!isset($config[$name])){
            return false;
        }
        $parameters = isset($config[$name]['parameters']) ? $config[$name]['parameters'] : array();
        if(empty($parameters)){
            return false;
        }
        if(count($parameters) == 1){
            $parameters = array_pop($parameters);
        }
        $options = isset($config[$name]['options']) ? $config[$name]['options'] : array();
        return new Predis\Client($parameters, $options);
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
            $t1 = microtime(true);
            $ret = call_user_func_array(array($this->client, 'evalsha'), $args);
            $t2 = microtime(true);
            Base_Bi::record_redis($this->name, 'eval', $args, '', '', $t2-$t1);
            return $ret;
        } catch (Exception $ex) {
            if($ex->getMessage() == 'NOSCRIPT No matching script. Please use EVAL.'){
                $args[0] = $script;
                return call_user_func_array(array($this->client, 'eval'), $args);
            }
            if('cli' != php_sapi_name()){
                var_dump($ex->getMessage());
                var_dump($ex->getTraceAsString());
            }else{
                $args[0] = $sha1;
                try{
                    foreach($args as $k => $v){
                        if(is_string($v) && ord($v) > 127){
                            $args[$k] = Util_Tool::decode($v);
                        }
                    }
                    $argsStr = Util_Tool::encode($args, 'json');
                } catch (Exception $ex) {
                    $argsStr = Util_Tool::encode($args, 'json');
                }
                $trace = json_encode($ex->getTraceAsString());
                $errorLog = Util_Tool::date()."\t".gethostname()."\tRedis\t{$argsStr}{$ex->getMessage()}<br/>{$trace}\n";
                file_put_contents(LOG_DIR . 'fatal_error.log', $errorLog, LOCK_EX | FILE_APPEND);
            }
            Base_Log::error($ex->getMessage()."\n".$ex->getTraceAsString());
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
            $t1 = microtime(true);
            $ret = call_user_func_array(array($client, $name), $arguments);
            $t2 = microtime(true);
            if($name != 'pipeline'){
                Base_Bi::record_redis($this->name, $name, $arguments, '', '', $t2-$t1);
            }
            return $ret;
        } catch (Exception $exc) {
            if(false !== ($pos = strpos($exc->getMessage(), 'Error while reading line from the server.'))){
                Base_Log::error("reconnecting to ".  $exc->getMessage());
                $this->client = self::connect($this->name);
                return call_user_func_array(array($client, $name), $arguments);
            }
            if('cli' != php_sapi_name()){
                var_dump($exc->getMessage());
                var_dump($exc->getTraceAsString());
            }
            Base_Log::error($exc->getMessage()."\t$name\t".json_encode($arguments)."\n".$exc->getTraceAsString());
            if($name != 'pipeline'){
                Base_Bi::record_redis($this->name, $name, $arguments, $exc->getMessage(), json_encode($exc->getTraceAsString()));
            }
            return false;
        }
    }
}