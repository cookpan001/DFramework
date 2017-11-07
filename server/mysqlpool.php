<?php
define('IN_SWOOLE', true);
include dirname(__DIR__).DIRECTORY_SEPARATOR.'base.php';

class Server
{
    private $serv;
    private $pool;
    private $protocol;

    public function __construct() {
        $this->serv = new swoole_server("0.0.0.0", 3307);
        $this->serv->set(array(
            'worker_num' => 8,
            'daemonize' => false,
            'max_request' => 10000,
            'dispatch_mode' => 2,
            'package_max_length' => 8192,
//            'open_length_check'=> true,
//            'package_length_offset' => 0,
//            'package_body_offset' => 4,
//            'package_length_type' => 'N',
            'open_eof_check' => true, //打开EOF检测
            'package_eof' => "\r\n", //设置EOF
            'log_file' => '/tmp/mysqlpool.log',
        ));
        $this->pool = new \DF\Async\MysqlPool(5);
        $this->protocol = new \DF\Protocol\Redis();
        $this->serv->on('Start', array($this, 'onStart'));
        $this->serv->on('Connect', array($this, 'onConnect'));
        $this->serv->on('Receive', array($this, 'onReceiveRedis'));
        $this->serv->on('Close', array($this, 'onClose'));
        $this->serv->start();
    }
    public function onStart( $serv ) {
        
    }
    public function onConnect( $serv, $fd, $from_id ) {
        
       
    }
    public function onReceiveN( swoole_server $serv, $fd, $from_id, $data ) {
        $len = unpack('N', $data)[1];
        $sql = substr($data, -$len);
        $this->pool->query($sql, function($db, $result) use ($serv, $fd){
            $head = array(
                $db->error,
                $db->errno,
                $db->insert_id,
                $db->affected_rows,
                count($result),
            );
            $fields = array();
            $data = array();
            if(empty($db->errno) && $result){
                foreach($result as $line){
                    if(empty($fields)){
                        $fields = array_keys($line);
                    }
                    $data[] = array_values($line);
                }
            }
            $str = json_encode(array($head, $fields, $data));
            $serv->send($fd, pack('N', strlen($str)).$str);
        });
    }
    
    public function onReceiveRedis(swoole_server $serv, $fd, $from_id, $data ) {
        $ret = $this->protocol->unserialize($data);
        foreach($ret as $commands){
            if(count($commands) < 3){
                $serv->send($fd, $this->protocol->serialize('-ERR command num incorrect'));
                return;
            }
            $cmd = strtolower(array_shift($commands));
            if('set' == $cmd){
                $master = 'master';
            }else if('get' == $cmd){
                $master = 'slave';
            }else{
                $serv->send($fd, $this->protocol->serialize(1));
                return;
            }
            $dbname = array_shift($commands);
            $this->pool->query(implode(' ', $commands), function($db, $result) use ($serv, $fd){
                $head = array(
                    $db->error,
                    $db->errno,
                    $db->insert_id,
                    $db->affected_rows,
                    count($result),
                );
                $fields = array();
                $data = array();
                if(empty($db->errno) && $result){
                    foreach($result as $line){
                        if(empty($fields)){
                            $fields = array_keys($line);
                        }
                        $data[] = array_values($line);
                    }
                }
                $str = $this->protocol->serialize(array($head, $fields, $data));
                $serv->send($fd, $str."\r\n");
            });
        }
    }
    public function onClose( $serv, $fd, $from_id ) {
        
    }
}
new Server();