<?php
namespace DF\Async;

class MySqlPoolSocket
{
    const SIZE = 1024;
    const TIMEOUT = 50;
    const RETRY = 3;
    
    private $port;
    private $host;
    private $socket = null;
    private $protocol;
    
    public function __construct($host, $port)
    {
        $this->protocol = new \DF\Protocol\Msgpack();
        $this->host = $host;
        $this->port = $port;
        $this->connect();
    }
    
    public function connect()
    {
        if($this->socket){
            socket_close($this->socket);
            $this->socket = null;
        }
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
    
    private function send($str, $retry = 0)
    {
        if($retry > self::RETRY){
            return false;
        }
        $n = socket_write($this->socket, $str, strlen($str));
        $errorCode = socket_last_error($this->socket);
        socket_clear_error($this->socket);
        if($n == 0 || (EPIPE == $errorCode || ECONNRESET == $errorCode)){
            $this->connect();
            $ret = $this->send($str, $retry + 1);
            return $ret;
        }
        return true;
    }
    
    private function receive($retry = 0)
    {
        $tmp = '';
        $num = socket_recv($this->socket, $tmp, self::SIZE, MSG_DONTWAIT);
        $errorCode = socket_last_error($this->socket);
        socket_clear_error($this->socket);
        if((0 === $errorCode && null === $tmp) || EPIPE == $errorCode || ECONNRESET == $errorCode){
            $this->connect();
            return $this->request($retry + 1);
        }
        return $tmp;
    }

    public function request($cmd, $dbname, $sql, $retry = 0)
    {
        if($retry > self::RETRY){
            return new MysqlResponse();
        }
        $str = $this->protocol->serialize([$cmd, $dbname, $sql]);
        $sendRet = $this->send($str);
        if(!$sendRet){
            return new MysqlResponse();
        }
        $tmp = '';
        $ret = '';
        //read
        $num = socket_recv($this->socket, $tmp, self::SIZE, MSG_DONTWAIT);
        $errorCode = socket_last_error($this->socket);
        socket_clear_error($this->socket);
        if((0 === $errorCode && null === $tmp) || EPIPE == $errorCode || ECONNRESET == $errorCode){
            $this->connect();
            return $this->request($cmd, $dbname, $sql, $retry + 1);
        }
        if(is_int($num) && $num > 0){
            $ret .= $tmp;
        }
        $timout = self::TIMEOUT;
        while(true){
            if($timout <= 0){
                if(!empty($ret)){
                    break;
                }
                return new MysqlResponse();
            }
            $num = socket_recv($this->socket, $tmp, self::SIZE, MSG_DONTWAIT);
            $errorCode = socket_last_error($this->socket);
            if(is_int($num) && $num > 0){
                $ret .= $tmp;
            }
            if((0 === $errorCode && null === $tmp) || EPIPE == $errorCode || ECONNRESET == $errorCode){
                $this->connect();
                return $this->request($cmd, $dbname, $sql, $retry + 1);
            }
            if (EAGAIN == $errorCode || EINPROGRESS == $errorCode) {
                if(!empty($ret)){
                    break;
                }
                $timout--;
                usleep(100);
                continue;
            }
            if(0 === $num){
                break;
            }
        }
        $response = $this->protocol->unserialize($ret);
        return new MysqlResponse($response[0]);
    }
    
    public static function test()
    {
//        $client = new self('127.0.0.1', 3307);
//        $response = $client->request('get', 'test', 'select * from user');
//        var_dump($response->result());
        $info = \DF\Data\User::getData();
        var_dump($info);
    }
    
    public static function testDF()
    {
        $info = \DF\Data\Goods::getData(['id' => 2]);
        var_dump($info);
    }
}
//include dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'base.php';
//MySqlPoolSocket::test();