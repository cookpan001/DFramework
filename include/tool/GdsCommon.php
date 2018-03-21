<?php
namespace DF\Tool;

use \DF\Base\Config;
use \DF\Util\Helper;

abstract class GdsCommon
{
    const CONFIG_GDS = Config::CONFIG_GDS;
    
    private static $all = array();
    
    public static function getGDSForQuery($gdsType)
    {
        if(!class_exists('Data\\Config\\Gds')){
            exit("class Data\\Config\\Gds not exits.");
        }
        $content = Data\Config\Gds::getLine($gdsType);
        if(!isset($content[$gdsType])){
            return array();
        }
        $data = Helper::uncompress($content[$gdsType]);
        $returnData = array();
        if (!isset($data) || count($data) <= 0)
        {
            return $returnData;
        }
        unset($data['fields']);
        unset($data['data_type']);
        $keys = self::getFields($gdsType);
        foreach ($data as $k => $v)
        {
            if (!is_array($v) || empty($v) || empty($keys))
            {
                continue;
            }           
            $newv = self::getInnerData($v);
            if(empty($newv))
            {
                continue;
            }
            $returnData = self::array_append($returnData, $newv);
        }
        return $returnData;
    }
    
    static function getInnerData($arr, $keys = false)
    {
        $cur = current($arr);        
        $type = gettype($cur);       
        if($type != 'array')
        {
            if(is_array($keys) && !empty($keys) && count($keys) == count($arr))
            {
                return array(array_combine($keys, $arr));
            }
            else
            {
                return array($arr);
            }
        }
        else
        {
            $ret = array();
            foreach($arr as $item)
            {
                $newv = self::getInnerData($item, $keys);
                $ret = self::array_append($ret, $newv);
            }
            return $ret;
        }
    }
    
    static function array_append($haystack, $needle)
    {
        foreach($needle as $item)
        {
            $haystack[] = $item;
        }
        return $haystack;
    }
    
    public static function getFields($gdsType)
    {
        $config = Config::getKey(self::CONFIG_GDS, $gdsType);
        return $config['fields'];
    }
    
    public static function getKeys($gdsType)
    {
        $config = Config::getKey(self::CONFIG_GDS, $gdsType);
        return $config['keys'];
    }
    
    public static function getDataType($gdsType)
    {
        $config = Config::getKey(self::CONFIG_GDS, $gdsType);
        return $config['data_type'];
    }
    /** used for multi-dimension array, not used now.
     */
    static function setById($gdsConfigType, &$input, $item, $needKey = false)
    {
        $ids = self::getKeys($gdsConfigType);
        if(false === $ids || !is_array($ids))
        {
            return false;
        }
        $keys = self::getFields($gdsConfigType);
        if(count($keys) != count($item))
        {
            return false;
        }
        $newItem = array_combine($keys, $item);
        $tmp = &$input;
        $i = 1;
        $idKeys = array();
        $newKeySearch = array();
        foreach($keys as $index => $key)
        {
            $idKeys[] = '{'.$index.'}';
            $newKeySearch[] = '{'.$key.'}';
        }
        foreach($ids as $id)
        {
            //eg: $data[$id][] = $line;
            if($id === '')
            {
                $tmp[] = $item;
                return true;
            }
            //eg: $data["$idx"._."$idy"] = $line;
            $newid = str_replace($idKeys, $item, $id);
            $newKey = str_replace($newKeySearch, $item, $newid);
            if($id != $newKey)//有替换发生
            {
                $value = $newKey;
            }
            else
            {
                $value = $newItem[$id];
            }
            //eg: $data[$id_1][$id_2]...[$id_xx] = $line;
            if(!array_key_exists($value, $tmp))
            {
                $tmp[$value] = array();
            }
            if($i == count($ids))
            {
                if($needKey)
                {
                    $tmp[$value] = $newItem;
                }
                else
                {
                    $tmp[$value] = $item;
                }
                return $tmp[$value];
            }
            $tmp = &$tmp[$value];
            $i++;
        }
    }
    
    public static function reload()
    {
        self::$all = array();
    }

    public static function getLine($gdsType, $key)
    {
        $data = self::get($gdsType);
        $fields = self::getFields($gdsType);
        if(empty($data)){
            return array();
        }
        if(!isset($data[$key])){
            return array();
        }
        if(!is_array(current($data[$key]))){
            return Helper::array_combine($fields, $data[$key]);
        }
        $ret = array();
        foreach($data[$key] as $id => $line){
            $ret[$id] = Helper::array_combine($fields, $line);
        }
        return $ret;
    }

    public static function get($gdsType, $raw = false)
    {
        if(isset(self::$all[$gdsType])){
            return self::$all[$gdsType];
        }
        if(empty($gdsType)){
            return array();
        }
        if(file_exists(STORAGE_PATH.'gds'.DIRECTORY_SEPARATOR.$gdsType)){
            $content = file_get_contents(STORAGE_PATH.'gds'.DIRECTORY_SEPARATOR.$gdsType);
            if($raw){
                return $content;
            }
            $ret = Helper::uncompress($content);
            if($raw){
                return $content;
            }
        }else{
            if(!class_exists('Data\\Config\\Gds')){
                exit("class Data\\Config\\Gds not exits.");
            }
            $data = Data\Config\Gds::getGds($gdsType);
            $result = array();
            if(!empty($data)){
                $result = array_shift($data);
            }
            if(empty($result)){
                self::$all[$gdsType] = array();
                return array();
            }
            if($raw){
                return $result['content'];
            }
            $ret = Helper::uncompress($result['content']);
        }
        self::$all[$gdsType] = $ret;
        return $ret;
    }
    
    public static function save($gdsType, $fileName)
    {
        if(!class_exists('Data\\Config\\Gds')){
            exit("class Data\\Config\\Gds not exits.");
        }
        $reader = new self($gdsType, $fileName);
        // validate csv
        $classname = 'Util\\Gds\\'.ucfirst($gdsType);
        if (class_exists($classname)) {
            $isValid = $classname::validate($gdsType, $reader);
        } else {
            $isValid = GdsValidator::validate($gdsType, $reader);
        }
        if(true !== $isValid){
            return $isValid;
        }
        $data = $reader->data();
        $data['fields'] = self::getFields($gdsType);
        $data['data_type'] = self::getDataType($gdsType);
        unset($reader);
        $arr = array(
            'type' => $gdsType,
            'content' => Helper::compress($data, -1),
        );
        return Data\Config\Gds::saveGds($arr);
    }
}