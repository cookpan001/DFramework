<?php
namespace DF\Base;

class Dispatcher
{
    private $pool = array();
    
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
        $arr = explode('/', $uri);
        if(count($arr) < 2){
            return 404;
        }
        $className = null;
        $module = lcfirst(array_shift($arr));
        $func = lcfirst(array_shift($arr));
        $isThreePart = false;
        if(!empty($arr)){
            //search module/func/action in INCLUDE_PATH first
            if(file_exists(INCLUDE_PATH . 'controller'. DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . $func . DIRECTORY_SEPARATOR . ucfirst($arr[0]) . '.php')){
                $action = array_shift($arr);
                $className = ROOT_NAMESPACE . '\\Controller\\' . ucfirst($module) . '\\' . ucfirst($func) . '\\' . ucfirst($action);
                $isThreePart = true;
            }
        }
        if($className && !class_exists($className)){
            $className = null;
        }
        //if no class found above, search for module/func/Index.php and module/func.php.
        if(empty($className)){
            if(file_exists(INCLUDE_PATH . 'controller'. DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . $func . DIRECTORY_SEPARATOR . 'Index.php')){
                $className[] = ROOT_NAMESPACE . '\\Controller\\' . ucfirst($module) . '\\' . ucfirst($func) . '\\Index';
            }
            if(file_exists(INCLUDE_PATH . 'controller'. DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . ucfirst($func) . '.php')){
                $className[] = ROOT_NAMESPACE . '\\Controller\\' . ucfirst($module) . '\\' . ucfirst($func);
            }
        }
        return array($className, $isThreePart, $arr);
    }

    public function run() {
        $arr = $this->getAction();
        if(!is_array($arr) || count($arr) < 2){
            \DF\Http\HttpHeader::status(404);
            return;
        }
        list($classes, $isThreePart, $param) = $arr;
        foreach((array)$classes as $className){
            if(!isset($this->pool[$className])){
                $obj = new $className;
                $this->pool[$className] = $obj;
            }else{
                $obj = $this->pool[$className];
            }
            $method = 'run';
            if(!$isThreePart && isset($param[0]) && method_exists($obj, $param[0].'Action')){
                $method = $param[0].'Action';
                array_shift($param);
            }else if(!method_exists($obj, $method)){
                continue;
            }
            return call_user_func_array(array($obj, $method), $param);
        }
        \DF\Http\HttpHeader::status(404);
        return;
    }
}