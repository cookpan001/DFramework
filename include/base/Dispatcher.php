<?php
namespace DF\Base;

class Dispatcher
{
    private $pool = array();
    //保存request_uri到class_name的映射
    private $map = array();
    
    public function __construct() {
        
    }
    
    private function getUri()
    {
        if(defined('IN_SWOOLE') && IN_SWOOLE){
            return trim($_SERVER['request']->server['request_uri'], '/');
        }
        $uri = filter_input(INPUT_SERVER, 'REQUEST_URI');
        $query = filter_input(INPUT_SERVER, 'QUERY_STRING');
        $tmp = str_replace('?'.$query, '', $uri);
        $requestUri = trim($tmp, '/');
        return $requestUri;
    }
    
    private function getAction()
    {
        $uri = $this->getUri();
        if(isset($this->map[$uri])){
            if(is_array($this->map[$uri])){
                return $this->map[$uri];
            }
            return 404;
        }
        $arr = explode('/', $uri);
        if(!isset($arr[1])){
            return 404;
        }
        if ($arr[1] == 'api') {
            if (count($arr) < 3) {
                return 404;
            }
            $className = ROOT_NAMESPACE . '\\Controller\\' . ucfirst($arr[0]) . '\\' . ucfirst($arr[2]);
            if (!isset($arr[3])) {
                $method = 'run';
            } else {
                $method = ucfirst(strtolower($arr[3])) . 'Action';
            }
        } else {
            if (count($arr) < 3) {
                return 404;
            }
            $className = ROOT_NAMESPACE . '\\Controller\\' . ucfirst($arr[0]) . '\\' . ucfirst($arr[1]);
            if (!isset($arr[2])) {
                $method = 'run';
            } else {
                $method = ucfirst(strtolower($arr[3])) . 'Action';
            }
        }
        $this->map[$uri] = array($className, $method);
        return $this->map[$uri];
    }

    public function run() {
        $arr = $this->getAction();
        if(!is_array($arr) || count($arr) < 2){
            \DF\Http\HttpHeader::status(404);
            return;
        }
        list($className, $method) = $arr;
        if(!isset($this->pool[$className])){
            $obj = new $className;
            $this->pool[$className] = $obj;
        }else{
            $obj = $this->pool[$className];
        }
        if(!method_exists($obj, $method)){
            \DF\Http\HttpHeader::status(404);
            return;
        }
        $obj->$method();
    }
}
