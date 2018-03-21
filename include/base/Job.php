<?php
namespace DF\Base;

class Job
{
    const TIMOUT = 3;
    
    public static function push($name, $data)
    {
        $str = gzdeflate(json_encode([$data]));
        $config = Config::getQueue();
        $i = array_rand($config['connection']);
        $line = $config['connection'][$i];
        if(extension_loaded('redis')){
            $redis = new \Redis();
            $redis->pconnect($line['host'], $line['port'], self::TIMOUT);
        } else if(class_exists('\Predis\Client')){
            $redis = new \Predis\Client("tcp://{$config[$name]['host']}:{$config[$name]['port']}");
        } else {
            $redis = new \DF\Sys\RedisClient($line['host'], $line['port'], self::TIMOUT);
        }
        if(!empty($line['password'])){
            $ret = $redis->auth($line['password']);
            if(!$ret || 'OK' != $ret){
                return false;
            }
        }
        try{
            return $redis->rpush($config['prefix'].$name, $str);
        } catch (\Throwable $ex) {
            return false;
        }
    }
}