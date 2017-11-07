<?php
namespace DF\Http;

class HttpHeader
{
    private static function setHeader($k, $v)
    {
        if(defined('IN_SWOOLE') && IN_SWOOLE){
            $_SERVER['response']->header($k, $v);
        }else{
            header($k . ':' . $v);
        }
    }
    
    public static function cookie($k, $v, $expire = 0, $path = '', $domain = '', $secure = false, $httponly = false)
    {
        if(defined('IN_SWOOLE') && IN_SWOOLE){
            $_SERVER['response']->header($k, $v, $expire, $path, $domain, $secure, $httponly);
        }else{
            setcookie($k, $v, $expire, $path, $domain, $secure, $httponly);
        }
    }
    
    public static function status($code)
    {
        if(defined('IN_SWOOLE') && IN_SWOOLE){
            $_SERVER['response']->status($code);
        }else{
            header('HTTP/1.1 '.$code);
        }
        $_SERVER['response']->end('');
    }

    public static function header($data)
    {
        if(empty($data)){
            return;
        }
        foreach($data as $k => $v){
            self::setHeader($k, $v);
        }
    }
}

