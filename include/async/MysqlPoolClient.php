<?php
namespace DF\Async;

class MySqlPoolClient
{
    private $client;
    private $protocol;
    
    public function __construct($host, $port)
    {
        $this->client = new \swoole_client(SWOOLE_TCP | SWOOLE_KEEP);
        $this->client->set(array(
//            'open_length_check'     => 1,
//            'package_length_type'   => 'N',
//            'package_length_offset' => 0,       //第N个字节是包长度的值
//            'package_body_offset'   => 4,       //第几个字节开始计算长度
//            'package_max_length'    => 2000000,  //协议最大长度
            'open_eof_check' => true, //打开EOF检测
            'package_eof' => "\r\n\r\n", //设置EOF
        ));
        $this->client->connect($host, $port);
        $this->protocol = new \DF\Protocol\Redis();
    }
    
    public function send($cmd, $dbname, $sql)
    {
        $this->client->send($this->protocol->serialize([$cmd, $dbname, $sql]));
    }
    
    public function recv()
    {
        $str = $this->client->recv();
        $ret = $this->protocol->unserialize(trim($str));
        return new MysqlResponse($ret[0]);
    }
    
    public static function test()
    {
        $client = new MysqlPoolClient('127.0.0.1', 3307);
        $client->send('get', 'test', 'select * from user');
        $response = $client->recv();
        var_dump($response->result());
    }
}
//$fp = fsockopen('127.0.0.1', 3307, $errno, $errstr, 30);
//if (!$fp) {
//    echo "$errstr ($errno)<br />\n";
//} else {
//    $sql = 'select * from user;';
//    var_dump($sql);
//    fwrite($fp, pack('N', strlen($sql)).$sql);
//    while (!feof($fp)) {
//        echo fgets($fp, 1024)."\n";
//    }
//    fclose($fp);
//}

MySqlPoolClient::test();