<?php
namespace DF\Base;

class Log
{
    public static function time()
    {
        list($micro, $second) = explode(microtime());
        return date('Y-m-d H:i:s') . substr($micro, 1);
    }

    public static function info()
    {
        
    }
    
    public static function notice()
    {
        
    }
    
    public static function error()
    {
        
    }
}
