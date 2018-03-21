<?php
define('IN_SWOOLE', true);
define('APP_NAME', 'mysqlpool');
include dirname(__DIR__).DIRECTORY_SEPARATOR.'base.php';

class MysqlPoolServer
{
    private $serv;
    private $pool;
    private $protocol;
    private $transactions = array();

    public function __construct($protocol = 'msgpack')
    {
        $this->serv = new swoole_server("0.0.0.0", 3307);
        $config = array(
            'worker_num' => 20,
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
        $this->serv->on('WorkerStart', array($this, 'onWorkerStart'));
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
            $count = count($arr);
            //协程，处理多条语句查询
            $coroutine = function() use ($count, $serv, $fd){
                $num = $count;
                $head = array(
                    //error, errno, insert_id, affected_rows, count
                    'error' => '', 
                    'errno' => 0,
                    'insert_id' => 0 ,
                    'affected_rows' => 0,
                    'count' => 0,
                );
                $fields = array();
                $data = array();
                while($num){
                    $send = (yield);
                    \DF\Base\Log::info("coroutine, 1");
                    list($db, $result) = $send;
                    //内部出错的时候，db表示错误的字符串
                    if(is_null($result)){
                        $head['error'] = $db;
                        $str = $this->protocol->serialize(array(array_values($head), [], []));
                        $serv->send($fd, $str);
                        return;
                    }
                    //mysql出错
                    if($db->errno){
                        $head['error'] = $db->error;
                        $head['errno'] = $db->errno;
                        $str = $this->protocol->serialize(array(array_values($head), [], []));
                        $serv->send($fd, $str);
                        return;
                    }
                    if($db->insert_id){
                        $head['insert_id'] = $db->insert_id > $head['insert_id'] ? $db->insert_id : $head['insert_id'];
                        continue;
                    }
                    if($db->affected_rows){
                        $head['affected_rows'] += $db->affected_rows;
                        continue;
                    }
                    foreach($result as $line){
                        if(empty($fields)){
                            $fields = array_keys($line);
                        }
                        $data[] = array_values($line);
                    }
                    $head['count'] += count($result);
                    --$num;
                }
                \DF\Base\Log::info("coroutine, 2");
                $str = $this->protocol->serialize(array(array_values($head), $fields, $data));
                \DF\Base\Log::info("coroutine, 3");
                $serv->send($fd, $str);
            };
            $gen = $coroutine();
            $processFunc = function($db, $result) use ($gen){
                $gen->send([$db, $result]);
                $this->pool->release($db);
            };
            foreach($arr as $sql) {
                $this->pool->query($sql, $fd, $dbname, $processFunc);
            }
        }
    }
    
    public function onWorkerStart($server, $worker_id)
    {
        $this->pool->init();
    }
}
new MysqlPoolServer();