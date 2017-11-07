<?php
include dirname(__DIR__).DIRECTORY_SEPARATOR.'base.php';

class Client
{
    private $client;
    
    public function __construct()
    {
        $this->client = new \Redis();
        $this->client->connect('127.0.0.1', 3307);
    }
    
    public function execute($cmd, $dbname, $sql)
    {
        $ret = $this->client->$cmd($dbname, $sql);
        var_dump($ret);
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
//$client = new Client();
//$client->execute('set', 'test', 'select * from user');
\DF\Async\MySqlPoolClient::testDF();