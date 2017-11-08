<?php
namespace DF\Async;

use \DF\Base\Config;

class MysqlPool
{
    protected $max = array();
    protected $config;
    protected $idle = array();
    protected $busy = array();
    protected $table2database = array();//缓存表名到库名的映射
    protected $connections = array();
    protected $transactions = array();//有哪些数据库连接在事务中
    protected $fd2db = array();//客户端连接对数据库连接的映射，考虑用在事务
    protected $waiting = array();//等待数据库连接的客户端


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
            call_user_func_array($callback, array('no config for database: '.$dbname, null));
            return null;
        }
        if(empty($this->idle[$dbname][$master])){
            $this->idle[$dbname][$master] = array();
        }
        if(empty($this->busy[$dbname][$master])){
            $this->busy[$dbname][$master] = array();
        }
        if(count($this->idle[$dbname][$master]) + count($this->busy[$dbname][$master]) >= $this->max[$master]){
            call_user_func_array($callback, array('max connection for database: '.$dbname, null));
            return null;
        }
        $conn = new \swoole_mysql();
        $conn->on('close', function ($db){
            $this->remove($db);
        });
        $i = array_rand($this->config[$dbname][$master]);
        $conn->connect($this->config[$dbname][$master][$i], function($db, $r) use ($dbname, $master, $sql, $callback){
            if(false !== $r){
                $hash = spl_object_hash($db);
                $this->busy[$dbname][$master][$hash] = $db;
                $this->connections[$hash] = array($dbname, $master);
                $this->execute($db, $sql, $callback);
            }else{
                call_user_func_array($callback, array('connection failed for database: '.$dbname, null));
            }
        });
    }
    
    public function execute($db, $sql, $callback)
    {
        $cmd = strtolower(trim($sql));
        if('begin' == $cmd){
            $this->transactions[spl_object_hash($db)] = 1;
        }
        $db->query($sql, $callback);
        if('commit' == $cmd || 'rollback' == $cmd){
            unset($this->transactions[spl_object_hash($db)]);
        }
    }

    public function release($conn)
    {
        $hash = spl_object_hash($conn);
        if(isset($this->transactions[$hash])){
            return false;
        }
        if(isset($this->connections[$hash])){
            list($dbname, $master) = $this->connections[$hash];
            $this->idle[$dbname][$master][$hash] = $conn;
            unset($this->busy[$dbname][$master][$hash]);
        }
        return true;
    }
    
    public function remove($conn)
    {
        $hash = spl_object_hash($conn);
        if(isset($this->connections[$hash])){
            list($dbname, $master) = $this->connections[$hash];
            unset($this->idle[$dbname][$master][$hash]);
            unset($this->busy[$dbname][$master][$hash]);
            unset($this->connections[$hash]);
        }
    }
    
    public function refresh($oldhash, $conn, $dbname, $master = 'master')
    {
        $hash = spl_object_hash($conn);
        $this->connections[$hash] = $conn;
        $this->idle[$dbname][$master][$hash] = $conn;
        unset($this->busy[$dbname][$master][$oldhash]);
        unset($this->idle[$dbname][$master][$oldhash]);
        unset($this->connections[$oldhash]);
    }
    
    public function getDb($sql)
    {
        $matches = array();
        $pattern = '#(?:(?:INSERT (?:[IGNORE\s+]*INTO))|UPDATE|(?:DELETE\s+FROM)|(?:SELECT\s+.*\s+FROM))\s+\`?(\w+)\`?#sim';
        preg_match($pattern, $sql, $matches);
        $tableName = array_pop($matches);
        $config = Config::getConfig(Config::CONFIG_TABLES);
        if(isset($config[$tableName]) && 1 == $config[$tableName]['num']){
            return key($config[$tableName]['database']);
        }
        $splitTableName = preg_replace('#_\d{2}#', '', $tableName);
        if(!isset($config[$splitTableName])){
            return '';
        }
        if(empty($config[$splitTableName]['database'])){
            return '';
        }
        if($splitTableName != $tableName){
            $index = (int)substr($tableName, -2);
            foreach($config[$splitTableName]['database'] as $db => $num){
                if($index < $num){
                    return $db;
                }
                $index -= $num;
            }
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
    public function query($sql0, $fd, $callback)
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
                $this->execute($conn, $sql, $callback);
                return;
            }
        }
        $this->create($dbname, $master, $sql, $callback);
    }
}