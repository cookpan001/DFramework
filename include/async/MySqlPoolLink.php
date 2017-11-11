<?php
namespace DF\Async;

class MySqlPoolLink
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
        $sock = pfsockopen($this->host, $this->port, $errno, $errstr, $timeout);
        if($errno){
            $sock = pfsockopen($this->host, $this->port, $errno, $errstr, $timeout);
        }
        $this->socket = $sock;
    }

    public function request($cmd, $dbname, $sql, $retry = 0)
    {
        if($retry > self::RETRY){
            return new MysqlResponse();
        }
        $this->connect();
        $str = $this->protocol->serialize([$cmd, $dbname, $sql]);
        $n = fwrite($this->socket, $str, strlen($str));
        $num = fread($this->socket, self::SIZE);
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
//include dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'base.php';
//MySqlPoolSocket::testDF();