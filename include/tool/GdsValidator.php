<?php
namespace DF\Tool;

class GdsValidator
{
    static $NEED_CHECK = false;
    static $GDS_KEYS_FLIP = array();
    static $GDS_KEYS = array();
    static $DATA_TYPE = array();
    
    public static function nextLine(GdsCommon $object)
    {
        $line = $object->next();
        return $line;
    }

    //check the whole data
    public static function validate($type, $object)
    {
        self::$NEED_CHECK = false;
        self::$GDS_KEYS_FLIP = false;
        self::$GDS_KEYS = GdsCommon::getFields($type);
        self::$DATA_TYPE = GdsCommon::getDataType($type);
        $header = self::nextLine($object);
        if(count($header) != count(self::$GDS_KEYS))
        {
            return 'incorrect number of gds file headers. Expected: '.count(self::$GDS_KEYS).', real: '. count($header);
        }
        $hasNext = $object->hasNext();
        if(empty($hasNext))
        {
            return 'At least one line data is needed.';
        }
        if(false === self::$NEED_CHECK)
        {
            self::$NEED_CHECK = array();
            foreach (self::$GDS_KEYS as $index => $key)
            {
                //check for all the *id field.
                if(substr(strtolower($key), -2) == 'id' && method_exists(get_called_class(), 'checkId'))
                {
                    self::$NEED_CHECK[$index] = $key;
                    continue;
                }
                if(preg_match('#(\w+)\d+$#', $key, $matches))
                {
                    if(count($matches) > 1)
                    {
                        self::$NEED_CHECK[$index] = $key;
                        continue;
                    }
                }
                $method = 'check'.ucfirst($key);
                if(method_exists(get_called_class(), $method))
                {
                    self::$NEED_CHECK[$index] = $key;
                }
                else if(self::$DATA_TYPE)
                {
                    self::$NEED_CHECK[$index] = $key;
                }
            }
        }
        if(empty(self::$NEED_CHECK))
        {
            return true;
        }
        $line = 1;
        while (false !== ($item = self::nextLine($object))){
            $ret = self::chkItem($item);
            if(true !== $ret)
            {
                return 'line: '.$line. ' , '.$ret;
            }
            $line++;
        }
        return true;
    }
    //check for single row
    private static function chkItem($item)
    {
        if(false === self::$GDS_KEYS_FLIP)
        {
            self::$GDS_KEYS_FLIP = array_flip(self::$GDS_KEYS);
        }
        if(count($item) != count(self::$GDS_KEYS))
        {
            return 'incorrect number of gds fields.';
        }
        foreach(self::$GDS_KEYS_FLIP as $key => $index)
        {
            $v = $item[self::$GDS_KEYS_FLIP[$key]];
            if(!empty(self::$DATA_TYPE[$index]) && strtolower(self::$DATA_TYPE[$index]) == 'int')
            {
                if(!is_int($v) && !ctype_digit($v))
                {
                    return $key . ' : ' . $v . ' , is not int.';
                }
            }
            if(!empty(self::$DATA_TYPE[$index]) && strtolower(self::$DATA_TYPE[$index]) == 'datetime')
            {
                if(FALSE === strtotime($v))
                {
                    return $key . ' : ' . $v . ' , is not datetime.';
                }
            }
            if(!isset(self::$NEED_CHECK[$index]))
            {
                continue;
            }
            if(substr(strtolower($key), -2) == 'id' && method_exists(get_called_class(), 'checkId'))
            {
                $ret = self::checkId($v);
                if(!$ret)
                {
                    return $key . ' : ' . $v;
                }
                continue;
            }
            if(preg_match('#(\w+)\d+$#', $key, $matches))
            {
                if(count($matches) > 1)
                {
                    $method = 'check'.ucfirst(strtolower($matches[1]));
                }
            }
            else
            {
                $method = 'check'.ucfirst(strtolower($key));
            }
            if(method_exists(get_called_class(), $method))
            {
                $ret = call_user_func_array(array(get_called_class(), $method), array($v));
                if(FALSE === $ret)
                {
                    return $key . ' : ' . $v;
                }
                else if(is_string($ret) && strlen($ret))
                {
                    return $ret;
                }
            }
        }
        //verify the logic relation between fields in one line.
        if(method_exists(get_called_class(), 'verifyLine'))
        {
            $ret = call_user_func_array(array(get_called_class(), 'verifyLine'), array($item));
            if(FALSE === $ret)
            {
                return 'config error in ' . var_export($item, true);
            }
            else if(is_string($ret) && strlen($ret))
            {
                return $ret;
            }
        }
        return true;
    }
    //check heroId,xxxId,ID,etc.
    private static function checkId($heroid)
    {
        if(ctype_digit($heroid) || is_int($heroid))
        {
            return true;
        }
        return false;
    }
    
    private static function checkLayout($layout)
    {
        if(preg_match("#\d+_\d+_\d+(:\d+_\d+_\d+){2}#",$layout))
        {
            return true;
        }
        return false;
    }
}