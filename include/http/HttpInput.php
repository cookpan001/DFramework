<?php
namespace DF\Http;

class HttpInput
{
    public static function get($name)
    {
        if(defined('IN_SWOOLE') && IN_SWOOLE){
            $value = isset($_SERVER['request']->get[$name]) ? $_SERVER['request']->get[$name] : '';
            $val = filter_var($value);
        }else{
            $val = filter_input(INPUT_GET, $name);
        }
        return $val;
    }
    
    public static function post($name)
    {
        if(defined('IN_SWOOLE') && IN_SWOOLE){
            $value = isset($_SERVER['request']->post[$name]) ? $_SERVER['request']->post[$name] : '';
            $val = filter_var($value);
        }else{
            $val = filter_input(INPUT_POST, $name);
        }
        return $val;
    }
    
    public static function server($name)
    {
        if(defined('IN_SWOOLE') && IN_SWOOLE){
            $n = strtolower($name);
            if(isset($_SERVER['request']->server[$n])){
                $value = $_SERVER['request']->server[$n];
            }else if(isset($_SERVER['request']->header[$n])){
                $value = $_SERVER['request']->header[$n];
            }else if(isset($_SERVER[$name])){
                $value = $_SERVER[$name];
            }else {
                return null;
            }
            $val = filter_var($value);
        }else{
            $val = filter_input(INPUT_SERVER, $name);
        }
        return $val;
    }
    
    public static function cookie($name)
    {
        if(defined('IN_SWOOLE') && IN_SWOOLE){
            $value = isset($_SERVER['request']->cookie[$name]) ? $_SERVER['request']->cookie[$name] : '';
            $val = filter_var($value);
        }else{
            $val = filter_input(INPUT_COOKIE, $name);
        }
        return $val;
    }
}