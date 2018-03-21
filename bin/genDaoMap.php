<?php

/**
 * 生成表名到处理类的映射
 *
 * @author pzhu
 */
class GenDaoMap
{
    protected $data = array();
    protected $basepath = '';


    public function __construct()
    {
        $this->basepath = INCLUDE_PATH . 'data';
        $this->subNamespace = 'Data';
    }
    
    public function readdir($path)
    {
        $ret = array();
        $handle = opendir($path);
        if (!$handle) {
            return $ret;
        }
        $namespace = '\\'.ROOT_NAMESPACE . '\\' . $this->subNamespace . str_replace(array($this->basepath, DIRECTORY_SEPARATOR), array('', '\\'), $path) . '\\';
        /* This is the correct way to loop over the directory. */
        while (false !== ($entry = readdir($handle))) {
            if('.' == $entry || '..' == $entry){
                continue;
            }
            if(is_file($path . DIRECTORY_SEPARATOR . $entry)){
                $classname = $namespace . substr($entry, 0, -4);
                if(!class_exists($classname)){
                    continue;
                }
                $this->data[$classname::TABLE_NAME] = $classname;
                continue;
            }
            $this->readdir($path . DIRECTORY_SEPARATOR . $entry);
        }
        closedir($handle);
    }


    public function run()
    {
        $this->readdir($this->basepath);
        asort($this->data);
        file_put_contents(COMMON_PATH . 'daomap.json', json_encode($this->data, JSON_PRETTY_PRINT), LOCK_EX);
    }
}
include dirname(__DIR__) . DIRECTORY_SEPARATOR . 'base.php';
$app = new GenDaoMap();
$app->run();