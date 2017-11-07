<?php
namespace DF\Async;

class MysqlResponse
{
    private $errno = 0;
    private $error = null;
    private $insert_id = 0;
    private $affected_rows = 0;
    private $count = 0;
    private $fields = array();
    private $result = array();
    
    public function __construct($arr = array())
    {
        if(empty($arr)){
            return;
        }
        if(!empty($arr[0]) && count($arr[0]) == 5){
            $this->error = $arr[0][0];
            $this->errno = $arr[0][1];
            $this->insert_id = $arr[0][2];
            $this->affacted_rows = $arr[0][3];
            $this->count = $arr[0][4];
        }
        if(!empty($arr[1])){
            $this->fields = $arr[1];
        }
        if(!empty($arr[2])){
            $this->result = $arr[2];
        }
    }
    
    public function error()
    {
        return $this->error;
    }
    
    public function errno()
    {
        return $this->errno;
    }
    
    public function insert_id()
    {
        return $this->insert_id;
    }
    
    public function affected_rows()
    {
        return $this->affected_rows;
    }
    
    public function fields()
    {
        return $this->fields;
    }
    
    public function response()
    {
        return $this->result;
    }
    
    public function result()
    {
        $ret = array();
        foreach($this->result as $line){
            $ret[] = array_combine($this->fields, $line);
        }
        return $ret;
    }
}