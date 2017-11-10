<?php
namespace DF\Async;

class MySqlPoolSocket
{
    const SIZE = 1024;
    const TIMEOUT = 5;
    const RETRY = 3;
    
    private $port;
    private $host;
    private $socket;
    private $protocol;
    
    public function __construct($host, $port)
    {
        $this->protocol = new \DF\Protocol\Redis();
        $this->host = $host;
        $this->port = $port;
        $this->connect();
    }
    
    public function connect()
    {
        $errno = 0;
        $errstr = '';
        $timeout = 2;
        $uri = "tcp://{$this->host}:{$this->port}";
        $flag = STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT;
        $sock = stream_socket_client($uri, $errno, $errstr, $timeout, $flag);
        if($errno){
            $sock = stream_socket_client($uri, $errno, $errstr, $timeout, $flag);
        }
        if($sock){
            $this->socket = socket_import_stream($sock);
            socket_set_nonblock($this->socket);
        }
    }
    
    public function write($str)
    {
        $ret = fwrite($this->socket, $str);
        if(!$ret){
            $this->connect();
            $ret = fwrite($this->socket, $str);
        }
        return $ret;
    }
    
    public function read()
    {
        $str = $this->client->recv();
        $ret = $this->protocol->unserialize(trim($str));
        return new MysqlResponse($ret[0]);
    }
    
    public function request($cmd, $dbname, $sql, $retry = 0)
    {
        if($retry > self::RETRY){
            return null;
        }
        $str = $this->protocol->serialize([$cmd, $dbname, $sql]);
        socket_write($this->socket, $str, strlen($str));
        $errorCode = socket_last_error($this->socket);
        socket_clear_error($this->socket);
        if((EPIPE == $errorCode || ECONNRESET == $errorCode)){
            socket_close($this->socket);
            $this->socket = null;
            $this->connect();
            return $this->request($cmd, $dbname, $sql, $retry + 1);
        }
        //read
        
        $tmp = '';
        $ret = '';
        $i = 0;
        $timout = self::TIMEOUT;
        while(true){
            if($timout <= 0){
                if(!empty($ret)){
                    break;
                }
                return false;
            }
            ++$i;
            $num = socket_recv($this->socket, $tmp, self::SIZE, MSG_DONTWAIT);
            if(is_int($num) && $num > 0){
                $ret .= $tmp;
            }
            $errorCode = socket_last_error($this->socket);
            socket_clear_error($this->socket);
            if((0 === $errorCode && null === $tmp) || EPIPE == $errorCode || ECONNRESET == $errorCode){
                socket_close($this->socket);
                $this->socket = null;
                $this->connect();
                //不能重新发请求，直接返回false
                return false;
            }
            if (EAGAIN == $errorCode || EINPROGRESS == $errorCode) {
                if(!empty($ret)){
                    break;
                }
                $timout--;
                usleep(500);
                continue;
            }
            if(0 === $num){
                break;
            }
        }
        $response = $this->protocol->unserialize(trim($ret));
        return new MysqlResponse($response[0]);
    }
    
    public static function test()
    {
        $client = new self('127.0.0.1', 3307);
        $response = $client->request('get', 'test', 'select * from user');
        //var_dump($response);
        var_dump($response->result());
    }
    
    public static function testDF()
    {
        $info = \DF\Data\Goods::getData(['id' => 2]);
        var_dump($info);
    }
}
include dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'base.php';
MySqlPoolSocket::testDF();