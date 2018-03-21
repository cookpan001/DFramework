<?php
namespace DF\Util;

/**
 * Description of PrintUtil
 *
 * @author pzhu
 */

class PrintUtil
{
    private static $tableContent = '';
    private static $tableHeader = '';
    private static $round = 0;
    
    public static function objectToArray($object)
    {
        if (!is_object($object) && !is_array($object))
        {
            return $object;
        }
        if (is_object($object))
        {
            $reflection = new ReflectionClass(get_class($object));
            $properties = $reflection->getProperties(ReflectionProperty::IS_PRIVATE);
            $tmp = array();
            foreach($properties as $property)
            {
                if($property->isStatic())
                {
                    continue;
                }
                $name = $property->getName();
                try{
                    $tmp[$name] = $object->$name;
                } catch (Exception $ex) {
                    $property->setAccessible(true);
                    $tmp[$name] = $property->getValue($object);
                }
            }
            $object = $tmp;
        }
        return array_map('self::objectToArray', $object);
    }
    
    private static function getInnerData($arr)
    {
        if(!is_array($arr))
        {
            self::$tableContent .= $arr;
            return true;
        }
        $typeArr = array_map('gettype', $arr);
        if(!in_array('array', $typeArr))
        {
            $tmp = "<tr><th>".implode("</th><th>", array_keys($arr))."</th></tr>";
            self::$tableContent .=  "<table border='1'>{$tmp}<tr>";
            foreach($arr as $key => $value)
            {
                if(!is_array($value))
                {
                    self::$tableContent .= "<td>$value</td>";
                }
                else
                {
                    self::$tableContent .= "<td>";
                    self::getInnerData($value);
                    self::$tableContent .= "</td>";
                }
            }
            self::$tableContent .=  "</tr></table>";
        }
        else
        {
            self::$round++;
            self::$tableContent .=  "<table border='1'>";
            $i = 0;
            foreach($arr as $k => $item)
            {
                self::$tableContent .= "<tr><td>$k</td><td>";
                self::getInnerData($item);
                self::$tableContent .= "</td></tr>";
                $i++;
            }
            self::$round--;
            self::$tableContent .= "</table>";
        }
    }
    
    static function printMultiDimensionArray($arr, $return = FALSE)
    {
        $arr = self::objectToArray($arr);
        self::$tableHeader = "";
        self::$tableContent = "";
        self::$round = 0;
        if(!is_array($arr))
        {
            if($return)
            {
                return $arr;
            }
            echo $arr;
            return true;
        }
        foreach ($arr as $k => $v)
        {
            if (!is_array($v) || empty($v))
            {
                //continue;
            }
            self::$tableContent .=  "<tr><td>$k</td><td>";
            self::getInnerData($v);
            self::$tableContent .= "</td></tr>";
        }
        self::$tableContent .= "</table>";
        $tmp = "<table border='1'>" . self::$tableContent;
        if($return)
        {
            return $tmp;
        }
        echo $tmp;
    }
    
    public static function printTable($arr)
    {
        $str = '<table border=1>';
        $associate = true;
        $keys = array_keys($arr);
        if(isset($arr[0])){
            $max = max($keys);
            if($max == count($arr) - 1){
                $associate = false;
            }
        }
        if($associate){
            $str .= '<tr><td>'.implode('</td><td>', $keys) . '</td></tr>';
        }
        $types = array_map('gettype', $arr);
        if(!in_array('array', $types)){
            $str .= '<tr><td>'.implode('</td><td>', $arr) . '</td></tr>';
        }else{
            foreach($arr as $v){
                $str .= '<tr>';
                if(is_array($v)){
                    $subtypes = array_map('gettype', $v);
                    if(!in_array('array', $subtypes)){
                        $str .= '<td>'.implode('</td><td>', $v) . '</td>';
                    }else{
                        $str .= '<td>'.self::printTable($v).'</td>';
                    }
                }else{
                    $str .= '<td>'.$v.'</td>';
                }
                $str .= '</tr>';
            }
        }
        $str .= '</table>';
        return $str;
    }
    
    public static function table($all, $fields = array(), $ops = array(), $queryFields = array(), $extraWhere = array())
    {
        if(empty($all)){
            return '参数为空';
        }
        $str = '<table border=1>';
        if(empty($fields)){
            $fields = array_keys(current($all));
        }
        if($ops){
            $str .= '<tr><td>'.implode('</td><td>', $fields).'</td><td>op</td></tr>';
        }else{
            $str .= '<tr><td>'.implode('</td><td>', $fields).'</td></tr>';
        }
        foreach((array)$all as $line){
            $str .= '<tr>';
            $where = $extraWhere;
            foreach($fields as $field){
                $str .= '<td>';
                if(!isset($line[$field])){
                    $str .= '';
                    continue;
                }
                if(is_array($line[$field])){
                    $str .= json_encode($line[$field], JSON_UNESCAPED_UNICODE);
                }else{
                    $str .= $line[$field];
                    if($ops){
                        if(is_int($line[$field]) || ctype_digit($line[$field])){
                            $where[] = "{$field}={$line[$field]}";
                        }else if($queryFields && in_array($field, $queryFields)){
                            $where[] = "{$field}={$line[$field]}";
                        }
                    }
                }
                $str .= '</td>';
            }
            if($ops){
                $str .= '<td>';
                $whereStr = implode('&', $where);
                foreach($ops as $op){
                    $str .= "<a href='?action=init&op=$op&$whereStr'>$op</a>&nbsp;&nbsp;";
                }
                $str .= '</td>';
            }else{
                $str .= '<td></td>';
            }
            $str .= '</tr>';
        }
        return $str .= '</table>';
    }
    
    public static function select($name, $values)
    {
        $str = "<select name='$name'>";
        foreach($values as $val){
            $str .= "<option value='$val'>$val</option>";
        }
        return $str . '</select>';
    }
}
