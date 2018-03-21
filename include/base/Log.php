<?php
namespace DF\Base;

class Log
{
    public static function time()
    {
        list($micro, $second) = explode(' ', microtime());
        return date('Y-m-d H:i:s', $second) . substr($micro, 1);
    }
    
    private static function msg($values)
    {
        if(defined('ENV')){
            $str = self::time() . "\t[" . ENV . ']';
        }else{
            $str = self::time();
        }
        foreach($values as $val){
            if(is_array($val)){
                $str .= "\t". json_encode($val);
            }else{
                $str .= "\t". $val;
            }
        }
        $str .= "\n";
        return $str;
    }
    
    private static function save($name, $str)
    {
        if(defined('APP_NAME')){
            if(is_writable(LOG_PATH . APP_NAME . '-' . $name.'-'.date('Ym').'.log')){
                file_put_contents(LOG_PATH . APP_NAME . '-' . $name.'-'.date('Ym').'.log', $str, LOCK_EX | FILE_APPEND);
            }
        }else{
            if(is_writable(LOG_PATH . $name.'-'.date('Ym').'.log')){
                file_put_contents(LOG_PATH . $name.'-'.date('Ym').'.log', $str, LOCK_EX | FILE_APPEND);
            }
        }
    }

    public static function error(...$values)
    {
        $str = self::msg($values);
        if(PHP_SAPI == 'cli'){
            global $STDERR;
            if($STDERR){
                fwrite($STDERR, $str);
                return;
            }
        }
        self::save('error', $str);
    }
    
    public static function info(...$values)
    {
        $str = self::msg($values);
        if(PHP_SAPI == 'cli'){
            global $STDOUT;
            if($STDOUT){
                fwrite($STDOUT, $str);
                return;
            }
        }
        self::save('info', $str);
    }

    public static function __callStatic($name, $arguments)
    {
        $str = self::msg($arguments);
        self::save($name, $str);
    }
}
