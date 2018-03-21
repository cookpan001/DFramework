<?php
namespace DF\Base;

class Config
{
    const CONFIG_REDIS = 'redis';
    const CONFIG_DB = 'db';
    const CONFIG_POOL = 'pool';
    const CONFIG_TABLES = 'tables';
    const CONFIG_CONNECTION = 'connection';
    const CONFIG_QUEUE = 'queue';
    const CONFIG_CRON = 'cron';
    const CONFIG_SEARCH = 'search';
    
    public static $pool = array();
    public static $storage = array();
    public static $common = array();
    /**
     * 各运行环境的配置
     * @param type $name
     * @return type
     */
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
    /**
     * common目录中的配置, 一般是通用的配置
     * @param type $name
     * @return type
     */
    public static function getCommon($name)
    {
        if(isset(self::$common[$name])){
            return self::$common[$name];
        }
        if(file_exists(COMMON_PATH . $name.'.json')){
            $config = json_decode(trim(file_get_contents(COMMON_PATH . $name.'.json')), true);
            self::$common[$name] = $config;
            return $config;
        }
        self::$common[$name] = array();
        return self::$common[$name];
    }

    public static function flush()
    {
        self::$pool = array();
        self::$storage = array();
        self::$common = array();
    }
    /**
     * 用connection.json中的配置替换db.json中定义的占位符
     * @return array
     */
    public static function getDb()
    {
        $type = self::CONFIG_DB;
        if(isset(self::$storage[$type])){
            return self::$storage[$type];
        }
        $dbConfig = self::getConfig($type);
        $connectionConfig = self::getCommon(self::CONFIG_CONNECTION);
        foreach($dbConfig as $name => $arr){
            foreach($arr as $master => $line){
                foreach($line as $k => $v){
                    if(is_string($v) && isset($connectionConfig[$type][$v])){
                        $dbConfig[$name][$master][$k] = $connectionConfig[$type][$v];
                    }
                }
            }
        }
        self::$storage[$type] = $dbConfig;
        return self::$storage[$type];
    }
    
    public static function getRedis()
    {
        $type = self::CONFIG_REDIS;
        if(isset(self::$storage[$type])){
            return self::$storage[$type];
        }
        $dbConfig = self::getConfig($type);
        $connectionConfig = self::getCommon(self::CONFIG_CONNECTION);
        foreach($dbConfig as $k => $v){
            if(is_string($v) && isset($connectionConfig[$type][$v])){
                $dbConfig[$k] = $connectionConfig[$type][$v];
            }
        }
        self::$storage[$type] = $dbConfig;
        return self::$storage[$type];
    }
    
    public static function getTables()
    {
        $name = self::CONFIG_TABLES;
        if(isset(self::$storage[$name])){
            return self::$storage[$name];
        }
        $config = array_merge(self::getConfig($name), self::getCommon($name));
        self::$storage[$name] = $config;
        return self::$storage[$name];
    }
    
    public static function getPool()
    {
        $type = self::CONFIG_POOL;
        if(isset(self::$storage[$type])){
            return self::$storage[$type];
        }
        $dbConfig = self::getConfig($type);
        $connectionConfig = self::getCommon(self::CONFIG_CONNECTION);
        foreach($dbConfig as $name => $arr){
            foreach($arr as $master => $line){
                foreach($line as $k => $v){
                    if(is_string($v) && isset($connectionConfig[$type][$v])){
                        $dbConfig[$name][$master][$k] = $connectionConfig[$type][$v];
                    }
                }
            }
        }
        self::$storage[$type] = $dbConfig;
        return self::$storage[$type];
    }
    
    public static function getQueue()
    {
        $type = self::CONFIG_QUEUE;
        if(isset(self::$storage[$type])){
            return self::$storage[$type];
        }
        $config = self::getConfig($type);
        $connectionConfig = self::getCommon(self::CONFIG_CONNECTION);
        $name = $config['connection'];
        if(is_string($name) && isset($connectionConfig[$type][$name])){
            $config['connection'] = $connectionConfig[$type][$name];
        }
        $prefix = isset($config['prefix']) ? $config['prefix'] : (isset($connectionConfig[$type]['prefix']) ? $connectionConfig[$type]['prefix'] : '');
        foreach($connectionConfig[$type]['handler'] as $k => $v){
            foreach((array)$prefix as $p){
                $config['handler'][$prefix.$k] = $v;
            }
            $config['handler'][$k] = $v;
        }
        $config['prefix'] = $prefix;
        self::$storage[$type] = $config;
        return self::$storage[$type];
    }
    
    public static function getSearch()
    {
        $type = self::CONFIG_SEARCH;
        if(isset(self::$storage[$type])){
            return self::$storage[$type];
        }
        $config = self::getConfig($type);
        $connectionConfig = self::getCommon(self::CONFIG_CONNECTION);
        self::$storage[$type] = $connectionConfig[$type][$config];
        return self::$storage[$type];
    }
}