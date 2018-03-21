<?php
class MyAutoload
{
    public $classMap = array(
        'DF' => array(INCLUDE_PATH),
        'DF\Admin' => array(ADMIN_PATH),
    );
    
    public function __autoload($class_name)
    {
        foreach($this->classMap as $namespace => $tmp){
            if(0 !== strpos($class_name, $namespace)){
                continue;
            }
            $left = str_replace($namespace, '', $class_name);
            $arr = explode('\\', trim($left, '\\'));
            $classFile = array_pop($arr);
            $dir = strtolower(implode(DIRECTORY_SEPARATOR, $arr));
            foreach($tmp as $path){
                $filepath = $path . $dir . DIRECTORY_SEPARATOR . $classFile . '.php';
                if(file_exists($filepath)){
                    require $filepath;
                    return true;
                }
            }
        }
        return false;
    }
}

spl_autoload_register(array(new MyAutoload, '__autoload'));