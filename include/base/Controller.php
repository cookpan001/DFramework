<?php
namespace DF\Base;

abstract class Controller
{
    protected $request;
    protected $response;
    
    public function __construct()
    {
        
    }
    
    public function init($request = null, $response = null)
    {
        if(defined('IN_SWOOLE') && IN_SWOOLE){
            $this->request = $request;
            $this->response = $response;
        }
    }
    
    public function output($data)
    {
        if(defined('IN_SWOOLE') && IN_SWOOLE){
            if(is_array($data)){
                $_SERVER['response']->end(json_encode($data, JSON_UNESCAPED_UNICODE));
            }else{
                $_SERVER['response']->end($data);
            }
        }else{
            echo json_encode($data, JSON_UNESCAPED_UNICODE);
        }
    }
}