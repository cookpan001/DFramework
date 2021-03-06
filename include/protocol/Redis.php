<?php
namespace DF\Protocol;

class Redis
{
    const END = "\r\n";
    
    public function encode(...$data)
    {
        return $this->serialize($data);
    }
    
    public function serialize($data)
    {
        if(is_string($data) && $data[0] == '-'){//Error
            return '-'.$data.self::END;
        }
        if($data instanceof \Exception){//Error
            return '-'.$data->getMessage().self::END;
        }
        if($data instanceof TimeoutException){
            return '*-1'.self::END;
        }
        if(is_int($data)){
            return ':'.$data.self::END;
        }
        if(is_string($data) && $data == 'OK'){
            return '+'.$data.self::END;
        }
        if(is_string($data)){
            return '$'.strlen($data).self::END.$data.self::END;
        }
        if(is_null($data)){
            return '$-1'.self::END;
        }
        $count = count($data);
        $str = '*'.$count.self::END;
        foreach($data as $line){
            if(is_null($line)){
                $str .= '$-1'.self::END;
            }else if(is_int($line)){
                $str .= ':'.$line.self::END;
            }else if(is_array($line)){
                $str .= $this->serialize($line);
            }else{
                $str .= '$'.strlen($line).self::END.$line.self::END;
            }
        }
        return $str;
    }
    
    public function parse(&$response, $totalLen, &$cur = 0)
    {
        $pos = strpos($response, self::END, $cur);
        if(false === $pos){
            if(0 === $cur){
                $ret = preg_split('#[ \t]#', $response);
                $cur = $totalLen;
                return $ret;
            }else{
                $pos = $totalLen;
            }
        }
        $ret = null;
        if(!isset($response[$cur])){
            return $ret;
        }
        switch ($response[$cur]) {
            case '-' : // Error message
                $ret = substr($response, $cur + 1, $pos - $cur - 1);
                $cur = $pos + 2;
                break;
            case '+' : // Single line response
                $ret = substr($response, $cur + 1, $pos - $cur - 1);
                $cur = $pos + 2;
                break;
            case ':' : //Integer number
                $ret = (int)substr($response, $cur + 1, $pos - $cur - 1);
                $cur = $pos + 2;
                break;
            case '$' : //bulk string or null
                $len = (int)substr($response, $cur + 1, $pos - $cur - 1);
                if($len == -1){
                    $ret = null;
                    $cur = $pos + 2;
                }else{
                    $ret = substr($response, $pos + 2, $len);
                    $cur = $pos + 2 + $len + 2;
                }
                break;
            case '*' : //Bulk data response
                $length = (int)substr($response, $cur + 1, $pos - $cur - 1);
                $cur = $pos + 2;
                if($length == -1){
                    $ret = array();//empty array
                    break;
                }
                if($length == 0){
                    $ret = array();//empty array
                    break;
                }
                for ($c = 0; $c < $length; $c ++) {
                    //$cur += 1;
                    //echo substr($response, $cur);
                    $ret[] = $this->parse($response, $totalLen, $cur);
                }
                break;
            default :
                $ret = substr($response, $cur, $pos - $cur);
                $cur = $pos + 2;
                break;
        }
        return $ret;
    }
    
    public function unserialize($str)
    {
        $ret = array();
        if("\r\n" == $str || "\r" == $str || "\n" == $str || '' == $str){
            return $ret;
        }
        $cur = 0;
        $len = strlen($str);
        while($cur < $len){
            $ret[] = $this->parse($str, $len, $cur);
        }
        return $ret;
    }
    
    private function read($conn)
    {
        if(!$conn)
        {
            return false;
        }
        $s = fgets($conn);
        return trim($s);
    }

    public function response($conn)
    {
        // Read the response
        $s = $this->read($conn);
        if(false === $s)
        {
            return false;
        }
        switch ($s[0])
        {
            case '-' : // Error message
                return substr($s, 1);
            case '+' : // Single line response
                return substr($s, 1);
            case ':' : //Integer number
                return (int)substr($s, 1);
            case '$' : //Bulk data response
                $i = (int)(substr($s, 1));
                if ($i == - 1)
                {
                    return null;
                }
                $buffer = '';
                if ($i == 0)
                {
                    $s = $this->read($conn);
                }
                while ($i > 0)
                {
                    $s = $this->read($conn);
                    $l = strlen($s);
                    $i -= $l;
                    if ($i < 0)
                    {
                        $s = substr($s, 0, $i);
                    }
                    $buffer .= $s;
                }
                return $buffer;
            case '*' : // Multi-bulk data (a list of values)
                $i = (int) (substr($s, 1));
                if ($i == - 1)
                {
                    return array();
                }
                if ($i == 0)
                {
                    return array();
                }
                $res = array();
                for ($c = 0; $c < $i; ++$c)
                {
                    $res[] = $this->response();
                }
                return $res;
            default :
                return false;
        }
    }
}
//$redis = new Redis;
//$str = "*3\r\n*5\r\n$-1\r\n:0\r\n:0\r\n:0\r\n:0\r\n*0\r\n*0";
//for($i=0;$i<20000;++$i){
//    $ret = $redis->unserialize($str);
//}