<?php
namespace DF\Tool;

class GdsStream
{
    const DELIMITER = ",";
    const ENCLOSER = '"';
    private $filename = '';
    private $handle = null;
    private $data = array();
    private $gdsType = '';
    private $lineNum = 0;
    private $dataType = array();

    public function __construct($gdsType, $filename)
    {
        $this->gdsType = $gdsType;
        $this->filename = $filename;
        $this->handle = fopen($filename, 'r');
        $this->dataType = GdsCommon::getDataType($gdsType);
    }
    
    public function next()
    {
        if(empty($this->handle)){
            return false;
        }
        $line = fgetcsv($this->handle, 0, self::DELIMITER, self::ENCLOSER);
        if(false === $line){
            fclose($this->handle);
            $this->handle = null;
        }else{
            ++$this->lineNum;
            if($this->lineNum > 1){
                self::up($line);
                if(!empty($this->dataType)){
                    foreach ($line as $i => $v){
                        if(isset($this->dataType[$i]) && strtolower($this->dataType[$i]) == 'int'){
                            $line[$i] = (int)$v;
                        }
                    }
                }
                GdsCommon::setById($this->gdsType, $this->data, $line);
            }
        }
        return $line;
    }
    
    public function hasNext()
    {
        if(empty($this->handle)){
            return false;
        }
        return true;
    }
    
    public function data()
    {
        return $this->data;
    }
    
    public function __destruct()
    {
        unset($this->data);
        if(!empty($this->handle)){
            fclose($this->handle);
            $this->handle = null;
        }
    }
    
    public static function down(&$item)
    {
        foreach($item as $k => $v){
            if(ctype_digit($v) || is_int($v)){
                continue;
            }
            if(false === (strpos($v, ','))){
                continue;
            }
            $item[$k] = str_replace(',', '|', $v);
        }
    }
    
    public static function up(&$item)
    {
        foreach($item as $k => $v){
            if(ctype_digit($v) || is_int($v)){
                continue;
            }
            if(false === (strpos($v, '|'))){
                continue;
            }
            $item[$k] = str_replace('|', ',', $v);
        }
    }
}
