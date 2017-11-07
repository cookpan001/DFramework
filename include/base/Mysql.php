<?php
namespace DF\Base;

class Mysql
{
    private $pass;
    private $user;
    private $link = null;
    private $errno = 0;
    private $error = '';
    private $host = '';
    private $port = '';
    private $db = '';
    
    public function __construct($host, $port, $user, $pass, $db)
    {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->pass = $pass;
        $this->db = $db;
    }
    
    public function connect()
    {
        $retry = 3;
        while($retry > 0 && !$this->link){
            //permenant connect
            $this->link = mysqli_connect('p:'.$this->host, $this->user, $this->pass, $this->db, $this->port);
            $retry--;
            usleep(1000);
        }
        if($this->link->connect_errno){
            Base_Log::sql($this->host.':'.$this->port, $this->link->connect_error);
            return null;
        }
        $this->link->set_charset("utf8mb4");
        return 0;
    }
    
    public function changeDb($dbName)
    {
        if($this->db == $dbName){
            return true;
        }
        $ret = $this->link->select_db($dbName);
        if(!$ret){
            $ret = $this->link->select_db($dbName);
        }
        $this->db = $dbName;
        return $ret;
    }

    /**
     * 
     * @param string $table : table name
     * @param array $where : where clause used in sql query
     * @param multi-array $option : 
     * id : result should be unique index by id
     * overwrite : same index should be override
     * limit : same as query sql 
     * order by: same as query sql 
     * @param array $fields : table fields
     * @return type
     */
    public function select($table, $where = array(), $option = array(), $fields = array())
    {
        $id = isset($option['id']) ? $option['id'] : '';
        $overwrite = isset($option['overwrite']) ? $option['overwrite'] : null;
        if(empty($fields)){
            $f = '*';
        }else{
            $fids = (array)$fields;
            if($id && !in_array($id, $fids)){
                $fids = array_merge($fids, (array)$id);
            }
            $fids = array_unique($fids);
            $f = $this->quote($fids);
        }
        $sql = 'SELECT ' . $f . ' FROM ' . $table . ' ';
        $i = 0;
        foreach($where as $k => $v){
            if($i == 0){
                $sql .= ' WHERE 1 ';
            }
            if(is_int($k)){
                $sql .= ' AND '.$v;
            }else if(is_array($v)){
                $sql .= " AND `$k` in ('" . implode("','", array_map(array($this, 'escape'), $v)) . "')";
            }else if(substr($v, 0) == '<' && substr($v, strlen($v) - 1) == '>') {
                $sql .= " AND `$k`=" . $this->escape(substr($v, 1, strlen($v) - 2));
            }else{
                $sql .= " AND `$k`='" . $this->escape($v) . "'";
            }
            ++$i;
        }
        if(isset($option['groupby'])){
            if(is_array($option['groupby'])){
                $sql .= ' GROUP BY ' . implode(',', $option['groupby']);
            }else{
                $sql .= ' GROUP BY ' . $option['groupby'];
            }
        }
        if(isset($option['having'])){
            if(is_array($option['having'])){
                $sql .= ' HAVING ' . implode(',', $option['having']);
            }else{
                $sql .= ' HAVING ' . $option['having'];
            }
        }
        if(isset($option['orderby'])){
            if(is_array($option['orderby'])){
                $sql .= ' ORDER BY ' . implode(',', $option['orderby']);
            }else{
                $sql .= ' ORDER BY ' . $option['orderby'];
            }
        }
        if(isset($option['limit'])){
            $offset = isset($option['offset']) ? $option['offset'] : 0;
            $sql .= " LIMIT {$offset}," . $option['limit'];
        }
        $value = isset($option['value']) ? $option['value']: '';
        if(isset($option['multi']) && $option['multi']){
            return $sql;
        }
        return $this->query($sql, $id, $overwrite, $value);
    }
    
    private function quote($fields)
    {
        $tmp = array();
        foreach($fields as $field){
            if(ctype_alnum($field)){
                $tmp[] = "`$field`";
            }else{
                $tmp[] = $field;
            }
        }
        return implode(',', $tmp);
    }
    
    public function replace($table, $arr, $rawKey = array(), $keys = array())
    {
        if(empty($arr)){
            return false;
        }
        $sql = 'REPLACE INTO '.$table;
        $tmp = array();
        $isBatch = false;
        $raw = array_flip($rawKey);
        foreach($arr as $k => $v){
            if(is_array($v)){
                if(empty($keys)){
                    $keys = array_keys($v);
                }
                $tmpv = array();
                foreach($v as $vk => $vv){
                    $tmpv[] = isset($raw[$vk]) ? $vv : "'" . $this->escape($vv) . "'";
                }
                $tmp[] = '(' .implode(',', $tmpv).')';
                $isBatch = true;
            }else{
                if(empty($keys)){
                    $keys = array_keys($arr);
                }
                $tmp[] = isset($raw[$k]) ? $v : "'" . $this->escape($v) . "'";
            }
        }
        $sql .= '(`'.implode('`,`', $keys) .'`) VALUES ';
        $sql .= $isBatch ? implode(',', $tmp) : '('.  implode(',', $tmp) . ')';
        return $this->query($sql);
    }
    
    public function insert($table, $arr, $update = array(), $rawKey = array(), $keys = array())
    {
        if(empty($arr)){
            return false;
        }
        $raw = array_flip($rawKey);
        $ignore = empty($update);
        if($ignore){
            $sql = 'INSERT IGNORE INTO '.$table;
        }else{
            $sql = 'INSERT INTO '.$table;
        }
        $tmp = array();
        $isBatch = false;
        foreach($arr as $k => $v){
            if(is_array($v)){
                if(empty($keys)){
                    $keys = array_keys($v);
                }
                $tmpv = array();
                foreach($v as $vk => $vv){
                    $tmpv[] = isset($raw[$vk]) ? $vv : "'" . $this->escape($vv) . "'";
                }
                $tmp[] = '(' .implode(',', $tmpv).')';
                $isBatch = true;
            }else{
                if(empty($keys)){
                    $keys = array_keys($arr);
                }
                $tmp[] = isset($raw[$k]) ? $v : "'" . $this->escape($v) . "'";
            }
        }
        $sql .= '(`'.implode('`,`', $keys) .'`) VALUES ';
        $sql .= $isBatch ? implode(',', $tmp) : '('.  implode(',', $tmp) . ')';
        if(!empty($update)){
            $duplicate = array();
            foreach($update as $k => $v){
                if(isset($raw[$k])){
                    $duplicate[] = "`{$k}`={$v}";
                }else if(preg_match('#\w+\(\w+\)#U', $v)){//MYSQL func
                    $duplicate[] = "`{$k}`={$v}";
                }else{
                    $v = $this->escape($v);
                    $duplicate[] = "`{$k}`='{$v}'";
                }
            }
            $sql .= ' ON DUPLICATE KEY UPDATE ' . implode(',', $duplicate);
        }
        return $this->query($sql);
    }
    
    public function update($table, $vals = array(), $where = array(), $rawKey = array())
    {
        if(empty($vals)){
            return 0;
        }
        $raw = array_flip($rawKey);
        $sql = "UPDATE $table SET ";
        $tmp = array();
        foreach($vals as $k => $v){
            if(is_int($k)){
                $tmp[] = "{$v}";
            }else if(isset($raw[$k])){
                $tmp[] = "`$k`=" . $v;
            }else if(preg_match('#\w+\(\.+\)#U', $v)){//MYSQL func
                $tmp[] = "`{$k}`={$v}";
            }else{
                $tmp[] = "`$k`='" . $this->escape($v) . "'";
            }
        }
        $sql .= implode(',',$tmp).' WHERE 1 ';
        foreach($where as $k => $v){
            if(is_array($v)){
                $sql .= " AND `$k` in ('" . implode("','", array_map(array($this, 'escape'), $v)) . "')";
            }else if(is_int($k)){
                $sql .= ' AND '.$v;
            }else{
                $sql .= " AND `$k`='" . $this->escape($v) . "'";
            }
        }
        return $this->query($sql);
    }
    
    public function delete($table, $where = array())
    {
        if(empty($where)){
            return true;
        }
        $sql = "DELETE FROM $table WHERE 1 ";
        foreach($where as $k => $v){
            if(is_array($v)){
                $sql .= " AND `$k` in ('" . implode("','", array_map(array($this, 'escape'), $v)) . "')";
            }else{
                $sql .= " AND `$k`='" . $this->escape($v) . "'";
            }
        }
        return $this->query($sql);
    }
    
    public function describe($table)
    {
        $sql = "DESC $table";
        return $this->query($sql);
    }
    
    public function tables()
    {
        $sql = "show tables";
        return $this->query($sql);
    }
    
    public function setNextId($table, $id)
    {
        $sql = "ALTER TABLE $table AUTO_INCREMENT=$id";
        return $this->query($sql);
    }
    
    public function escape($v)
    {
        return mysqli_escape_string($this->link, $v);
    }
    
    public function query($sql, $id = '', $overwrite = null, $value = '')
    {
        $t1 = microtime(true);
        $ret = $this->link->query($sql);
        $t2 = microtime(true);
        //Base_Log::sql($this->host.':'.$this->port, $sql."\t".($t2 - $t1));
        if($this->link->errno == 2006 //MySQL server has gone away
        || $this->link->errno == 2013 //Lost connection to MySQL server during query
        || $this->link->errno == 2048 //Invalid connection handle
        || $this->link->errno == 2055)//Lost connection to MySQL server at '%s', system error: %d
        {
            $this->link = null;
            $this->connect();
            $ret = $this->link->query($sql);
        }
        if(is_bool($ret)){
            if(false === $ret){
                throw new \Exception($this->link->error . "\nSQL:{$sql}\n", $this->link->errno);
            }
            return $ret;
        }
        return $this->parseResult($ret, $id, $value, $overwrite);
    }
    
    public function multiQuery($sql, $id = '', $overwrite = null, $value = '')
    {
        if(is_array($sql)){
            if(count($sql) == 1){
                $sql = array_pop($sql);
                return $this->query($sql, $id, $overwrite, $value);
            }
            $sql = implode(';', $sql);
        }
        $t1 = microtime(true);
        $ret = $this->link->multi_query($sql);
        $t2 = microtime(true);
        //Base_Log::sql($this->host.':'.$this->port, $sql."\t".($t2 - $t1));
        if($this->link->errno == 2006 //MySQL server has gone away
        || $this->link->errno == 2013 //Lost connection to MySQL server during query
        || $this->link->errno == 2048 //Invalid connection handle
        || $this->link->errno == 2055)//Lost connection to MySQL server at '%s', system error: %d
        {
            $this->link = null;
            $this->connect();
            $ret = $this->link->multi_query($sql);
        }
        if(is_bool($ret)){
            if(false === $ret){
                throw new \Exception($this->link->error . "\nSQL:{$sql}\n", $this->link->errno);
            }
        }
        $arr = array();
        do {
            $result = $this->link->store_result();
            if ($result) {
                $tmp = $this->parseResult($result, $id, $value, $overwrite);
                if(isset($tmp[0])){
                    $arr = array_merge($arr, $tmp);
                }else{
                    $arr += $tmp;
                }
            }
            if(!$this->link->more_results()){
                break;
            }
        } while ($this->link->next_result());
        return $arr;
    }
    
    private function parseResult($ret, $id, $value, $overwrite)
    {
        $result = array();
        while($row = $ret->fetch_assoc()){
            if(!empty($value)){
                if(is_array($value)){
                    foreach($value as $v){
                        $line[$v] = $row[$v];
                    }
                }else{
                    $line = $row[$value];
                }
            }else{
                $line = $row;
            }
            if(empty($id)){
                $result[] = $line;
                continue;
            }
            if(!is_array($id)){
                if(isset($row[$id])){
                    if(is_null($overwrite) || $overwrite){
                        $result[$row[$id]] = $line;
                    }else{
                        $result[$row[$id]][] = $line;
                    }
                }else{
                    $result[] = $line;
                }
                continue;
            }
            $tmp = &$result;
            foreach($id as $subid){
                if(!isset($tmp[$row[$subid]])){
                    $tmp[$row[$subid]] = array();
                }
                $tmp = &$tmp[$row[$subid]];
            }
            if($overwrite){
                $tmp = $line;
            }else{
                $tmp[] = $line;
            }
            unset($tmp);
        }
        $ret->free();
        return $result;
    }

    public function affectedRow()
    {
        
        return $this->link->affected_rows;
    }
    
    public function insertId()
    {
        return $this->link->insert_id;
    }
    
    public function transaction($func)
    {
        $this->link->begin_transaction();
        if($func($this)){
            $this->link->commit();
        }else{
            $this->link->rollback();
        }
    }
    
    public function __destruct()
    {
        if(!empty($this->link)){
            $this->link->close();
            $this->link = null;
        }
        try {
            //throw new Exception;
        } catch (Exception $exc) {
            
        }
    }
}
