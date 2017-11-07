<?php
namespace DF\Async;

use \DF\Base\Config;

class MysqlPool
{
    protected $max = array();
    protected $config;
    protected $idle = array();
    protected $busy = array();
    
    public function __construct($max = 5)
    {
        $this->config = Config::getConfig(Config::CONFIG_DB);
        $this->max['master'] = $max;
        $this->max['slave'] = $max * 2;
    }
    
    function close()
    {
        foreach ($this->resourcePool as $conn)
        {
            $conn->close();
        }
    }

    function __destruct()
    {
        $this->close();
    }
    
    public function create($dbname, $master = 'master', $sql = '', $callback = null)
    {
        if(!isset($this->config[$dbname][$master])){
            return null;
        }
        if(empty($this->idle[$dbname][$master])){
            $this->idle[$dbname][$master] = array();
        }
        if(empty($this->busy[$dbname][$master])){
            $this->busy[$dbname][$master] = array();
        }
        if(count($this->idle[$dbname][$master]) + count($this->busy[$dbname][$master]) >= $this->max[$master]){
            return null;
        }
        $conn = new \swoole_mysql();
        $conn->on('close', function ($db){
            $this->remove($db);
        });
        $i = array_rand($this->config[$dbname][$master]);
        $conn->connect($this->config[$dbname][$master][$i], function($db, $r) use ($dbname, $master, $sql, $callback){
            if(false !== $r && $callback){
                $this->busy[$dbname][$master][spl_object_hash($db)] = $db;
                $db->query($sql, $callback);
            }
        });
    }
    
    public function release($conn, $dbname, $master = 'master')
    {
        $hash = spl_object_hash($conn);
        $this->idle[$dbname][$master][$hash] = $conn;
        unset($this->busy[$dbname][$master][$hash]);
    }
    
    public function remove($conn, $dbname, $master = 'master')
    {
        $hash = spl_object_hash($conn);
        unset($this->idle[$dbname][$master][$hash]);
        unset($this->busy[$dbname][$master][$hash]);
    }
    
    public function refresh($oldhash, $conn, $dbname, $master = 'master')
    {
        $hash = spl_object_hash($conn);
        $this->idle[$dbname][$master][$hash] = $conn;
        unset($this->busy[$dbname][$master][$oldhash]);
        unset($this->busy[$dbname][$master][$oldhash]);
    }
    
    public function getDb($sql)
    {
        $matches = array();
        $pattern = '#(?:(?:INSERT (?:[IGNORE\s+]*INTO))|UPDATE|(?:DELETE\s+FROM)|(?:SELECT\s+.*\s+FROM))\s+\`?(\w+)\`?#sim';
        preg_match($pattern, $sql, $matches);
        $tableName = array_pop($matches);
        $config = Config::getConfig(Config::CONFIG_TABLES);
        if(!isset($config[$tableName])){
            return '';
        }
        if(empty($config[$tableName]['database'])){
            return '';
        }
        if(1 == $config[$tableName]['num']){
            return key($config[$tableName]['database']);
        }
        //TODO 多表路由
        return '';
    }
    /**
     * 
     * @param type $sql0
     * @param $callback
     * @return type
     */
    public function query($sql0, $callback)
    {
        $sql = trim($sql0);
        $prefix = strtolower(substr($sql, 0, 7));
        if($prefix == 'select'){
            $master = 'slave';
        }else{
            $master = 'master';
        }
        $dbname = $this->getDb(trim($sql));
        if(isset($this->idle[$dbname][$master])){
            foreach($this->idle[$dbname][$master] as $hash => $conn){
                unset($this->idle[$dbname][$master][$hash]);
                $this->busy[$dbname][$master][$hash] = $conn;
                $conn->query($sql, $callback);
                return;
            }
        }
        $this->create($dbname, $master, $sql, $callback);
    }
}