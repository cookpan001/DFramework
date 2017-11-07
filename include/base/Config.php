<?php
namespace DF\Base;

class Config
{
    const CONFIG_REDIS = 'redis';
    const CONFIG_DB = 'db';
    const CONFIG_TABLES = 'tables';
    
    public static $pool = array();

    public static function getConfig($name)
    {
        if(isset(self::$pool[$name])){
            return self::$pool[$name];
        }
        if(file_exists(CONFIG_PATH . $name.'.json')){
            $config = json_decode(trim(file_get_contents(CONFIG_PATH . $name.'.json')), true);
            self::$pool[$name] = $config;
            return $config;
        }
        self::$pool[$name] = array();
        return self::$pool[$name];
    }
    
    public static function flush()
    {
        self::$pool = array();
    }
}