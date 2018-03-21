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
        $this->config = Config::getDb();
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
    
    public function init()
    {
        $master = 'slave';
        foreach($this->config as $dbname => $arr){
            $i = array_rand($arr[$master]);
            $conn = new \swoole_mysql();
            $conn->on('close', function ($db){
                $this->remove($db);
            });
            $conn->connect($this->config[$dbname][$master][$i], function($db, $r) use ($dbname, $master){
                if(false === $r){
                    return;
                    call_user_func_array($callback, array('connection failed for database: '.$dbname, null));
                }
                $hash = spl_object_hash($db);
                $this->busy[$dbname][$master][$hash] = $db;
                $this->connections[$hash] = array($dbname, $master);
                $this->execute($db, 'show tables', function($db, $result) use ($dbname){
                    if(false === $result){
                        return;
                    }
                    foreach($result as $arr){
                        $this->table2database[array_pop($arr)] = $dbname;
                    }
                });
            });
        }
    }
    /**
     * 创建一条连接
     * @param type $dbname
     * @param type $master
     * @param type $sql
     * @param type $callback
     * @return type
     */
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
            $this->waiting[$dbname][$master][] = array($sql, $callback);
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
    /**
     * 把连接放回到池中，如果有排队的SQL则直接执行
     * @param type $conn
     * @return boolean
     */
    public function release($conn)
    {
        if(is_string($conn)){
            var_dump($conn);
        }
        $hash = spl_object_hash($conn);
        \DF\Base\Log::info("release {$hash}");
        if(isset($this->transactions[$hash])){
            return false;
        }
        if(isset($this->connections[$hash])){
            list($dbname, $master) = $this->connections[$hash];
            if(empty($this->waiting[$dbname][$master])){//没有排队的SQL查询
                $this->idle[$dbname][$master][$hash] = $conn;
                unset($this->busy[$dbname][$master][$hash]);
            }else{
                list($sql, $callback) = array_shift($this->waiting[$dbname][$master]);
                $this->execute($conn, $sql, $callback);
            }
        }
        return true;
    }
    /**
     * 删除一条数据库连接
     * @param type $conn
     */
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
    /**
     * 解析出SQL所在的表名和数据库名
     * @param type $sql
     * @return string
     */
    public function getDb($sql)
    {
        $matches = array();
        $pattern = '#(?:(?:INSERT (?:[IGNORE\s+]*INTO))|UPDATE|(?:DELETE\s+FROM)|(?:SELECT\s+.*\s+FROM))\s+\`?(\w+)\`?#sim';
        preg_match($pattern, $sql, $matches);
        $tableName = array_pop($matches);
        if(isset($this->table2database[$tableName])){
            return $this->table2database[$tableName];
        }
        $config = Config::getTables();
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
     * 执行SQL, 对SERVER的接口
     * @param type $sql0
     * @param $callback
     * @return type
     */
    public function query($sql0, $fd, $dbname, $callback)
    {
        \DF\Base\Log::info("$fd, query");
        $sql = trim($sql0);
        $prefix = strtolower(substr($sql, 0, 7));
        if($prefix == 'select'){
            $master = 'slave';
        }else{
            $master = 'master';
        }
        if(empty($dbname)){
            $dbname = $this->getDb($sql);
            \DF\Base\Log::info("$fd, getDb");
        }
        if(isset($this->idle[$dbname][$master])){
            foreach($this->idle[$dbname][$master] as $hash => $conn){
                unset($this->idle[$dbname][$master][$hash]);
                $this->busy[$dbname][$master][$hash] = $conn;
                $this->execute($conn, $sql, $callback);
                return;
            }
        }
        $this->create($dbname, $master, $sql, $callback);
        \DF\Base\Log::info("$fd, create");
    }
}