<?php
define('IN_SWOOLE', true);
include dirname(__DIR__).DIRECTORY_SEPARATOR.'base.php';

class Server
{
    private $serv;
    private $pool;
    private $protocol;
    private $transactions = array();

    public function __construct($protocol = 'msgpack')
    {
        $this->serv = new swoole_server("0.0.0.0", 3307);
        $config = array(
            'worker_num' => 8,
            'daemonize' => false,
            'max_request' => 10000,
            'dispatch_mode' => 2,
            'package_max_length' => 8192,
        );
        if('redis' == $protocol){
            $config += array(
                'open_eof_check' => true, //打开EOF检测
                'package_eof' => "\r\n", //设置EOF
            );
            $this->protocol = new \DF\Protocol\Redis();
        }else{
            $config += array(
                'open_length_check'=> true,
                'package_length_offset' => 0,
                'package_body_offset' => 4,
                'package_length_type' => 'N',
            );
            $this->protocol = new \DF\Protocol\Msgpack();
        }
        $this->serv->set($config);
        $this->pool = new \DF\Async\MysqlPool(50);
        $this->serv->on('Receive', array($this, 'onReceive'));
        $this->serv->start();
    }
    
    public function onReceive(swoole_server $serv, $fd, $from_id, $data ) {
        $ret = $this->protocol->unserialize($data);
        foreach($ret as $commands){
            if(count($commands) < 3){
                $default = $this->protocol->serialize(array(array('command num incorrect', 0, 0, 0, 0), array(), array()));
                $serv->send($fd, $default);
                return;
            }
            $cmd = strtolower(array_shift($commands));
            if('set' == $cmd){
                $master = 'master';
            }else if('get' == $cmd){
                $master = 'slave';
            }else{
                $default = $this->protocol->serialize(array(array('command not support', 0, 0, 0, 0), array(), array()));
                $serv->send($fd, $default);
                return;
            }
            $dbname = array_shift($commands);
            if(is_array($commands[0])){
                $arr = $commands[0];
            }else{
                $arr = $commands;
            }
            foreach($arr as $sql) {
                $this->pool->query($sql, $fd, $dbname, function($db, $result) use ($serv, $fd){
                    //出错的时候，db表示错误的字符串
                    if(is_null($result)){
                        $head = array(
                            $db,1,0,0,0,
                        );
                        $str = $this->protocol->serialize(array($head, [], []));
                        $serv->send($fd, $str);
                        return;
                    }
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
                    $serv->send($fd, $str);
                    $this->pool->release($db);
                });
            }
        }
    }
}
new Server();