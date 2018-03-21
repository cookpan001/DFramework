<?php
namespace DF\Base;

/**
 * Description of Cron
 *
 * @author pzhu
 */
class Cron
{
    const SUB_NAMESPACE = 'Cron';
    const BASE_PATH = INCLUDE_PATH . 'cron';
    
    protected $startTime = 0;

    public function __construct()
    {
        $this->startTime = microtime(true);
        register_shutdown_function(array($this, 'onFatalError'));
    }
    
    public function __set($name, $value)
    {
        $this->$name = $value;
    }

    public function onFatalError(){
        $error = error_get_last();
        if(empty($error)){
            return;
        }
        $this->error(json_encode($error, JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }
    
    public function errorHandler($errno, $errstr, $errfile, $errline, $errcontext)
    {
        try {
            throw new \Exception;
        } catch (\Throwable $exc) {
            $errcontext = $exc->getTraceAsString();
            $str = sprintf("%s:%d\nerrcode:%d\t%s\n%s\n", $errfile, $errline, $errno, $errstr, $errcontext);
            $this->error($str);
        }
        return true;
    }
    
    protected function log(...$values)
    {
        \DF\Base\Log::cron('[info]', static::class, ...$values);
    }
    
    protected function error(...$values)
    {
        \DF\Base\Log::cron('[error]', static::class, ...$values);
    }
    
    public static function jobList()
    {
        $data = array();
        self::readdir(self::BASE_PATH, $data);
        return $data;
    }
    
    private static function readdir($path, &$data = array())
    {
        $ret = array();
        $handle = opendir($path);
        if (!$handle) {
            return $ret;
        }
        $namespace = '\\'.ROOT_NAMESPACE . '\\' . self::SUB_NAMESPACE . str_replace(array(self::BASE_PATH, DIRECTORY_SEPARATOR), array('', '\\'), $path) . '\\';
        while (false !== ($entry = readdir($handle))) {
            if('.' == $entry || '..' == $entry){
                continue;
            }
            if(is_file($path . DIRECTORY_SEPARATOR . $entry)){
                $classname = $namespace . substr($entry, 0, -4);
                if(!class_exists($classname)){
                    continue;
                }
                $data[strtolower(substr($entry, 0, -4))] = $classname;
                continue;
            }
            $this->readdir($path . DIRECTORY_SEPARATOR . $entry);
        }
        closedir($handle);
    }
    
    public static function dispatch($className)
    {
        $config = \DF\Base\Config::getCommon(\DF\Base\Config::CONFIG_CRON);
        $key = substr($className, strrpos($className, '\\') + 1);
        $obj = new $className();
        foreach($config as $type => $arr){
            if(strtolower($key) == strtolower($type)){
                foreach($arr as $k => $v){
                    $obj->$k = $v;
                }
                break;
            }
        }
        $obj->run();
    }
}
