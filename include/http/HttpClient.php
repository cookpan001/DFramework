<?php
namespace DF\Component;

class HttpClient
{
    const TIME_OUT = 2;
    
    private static function init()
    {
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_TIMEOUT => self::TIME_OUT,
        ));
        return $ch;
    }
    
    public static function get($url)
    {
        $ch = self::init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        $response = curl_exec($ch);
        return $response;
    }
    
    public static function post($url, $data = array(), $multipart = true)
    {
        $ch = self::init();
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_URL, $url);
        if(is_array($data)){
            if($multipart){
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }else{
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Content-type:application/x-www-form-urlencoded'
                ), $ch);
            }
        }else{
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        $response = curl_exec($ch);
        return $response;
    }
    
    public static function put($url, $data)
    {
        $ch = self::init();
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        $response = curl_exec($ch);
        return $response;
    }
    
    public static function delete($url)
    {
        $ch = self::init();
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_URL, $url);
        $response = curl_exec($ch);
        return $response;
    }
}
