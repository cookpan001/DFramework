<?php
include dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'base.php';

class Commander
{
    /**
     * 进程名, 文件名, 与config_path中的*.json名字要求一致
     * @var type 
     */
    private static $processes = array(
        'queue' => 'queue',
    );
    /**
     * 各进程运行时的前缀, 用于在ps aux中找到它们, 或者启动它们
     * @var type 
     */
    private static $cmd = array(
        'queue' => 'php ' . SERVER_PATH . 'queue.php { name }',
    );
    /**
     * 获取本机IP
     * @return type
     */
    public static function getIp()
    {
        $ret = array();
        $ips = array();
        exec("if [ -e /sbin/ip ];then /sbin/ip -4 addr; elif [ \"x\" != \"x`which ip`\" ];then `which ip` -4 addr; else ifconfig | grep inet; fi;", $ret);
        foreach($ret as $line){
            $line = trim($line);
            if('inet ' !== substr($line, 0, 5)){
                continue;
            }
            $ip0 = substr($line, 5);
            $pos = strpos($ip0, '/');
            if(false !== $pos){
                $ip = substr($ip0, 0, $pos);
            }else{
                $ip = substr($ip0, 0, strpos($ip0, ' '));
            }
            if(substr($ip, 0, 3) == '127'){
                //continue;
            }
            $ips[$ip] = $ip;
        }
        return $ips;
    }
    /**
     * 获取CONFIG_PATH下的各配置信息, 根据 self::$processes
     * @return int
     */
    public static function getConf()
    {
        $ips = self::getIp();
        $summary = array();
        foreach(self::$processes as $name){
            $json = json_decode(file_get_contents(CONFIG_PATH . $name . '.json'), true);
            $count = count($json['host']);
            $workerNum = $json['worker'];
            $num = ceil($workerNum / $count);
            foreach($json['host'] as $host){
                if($workerNum <= 0){
                    break;
                }
                if($workerNum >= $num){
                    $summary[$host][$name] = array($workerNum, $num);
                    $workerNum -= $num;
                }else{
                    $summary[$host][$name] = array($workerNum, $workerNum);
                    $workerNum = 0;
                }
            }
        }
        $ret = array();
        foreach($ips as $ip){
            if(!isset($summary[$ip])){
                continue;
            }
            $ret = $summary[$ip];
            break;
        }
        return $ret;
    }
    /**
     * 获取前缀, 用于在ps aux 或 ps -ef 中查找到指定的进程
     * @param type $type
     * @return type
     */
    public static function getPrefix($type)
    {
        $search = array('{ ini_file }', '{ name }', SERVER_PATH);
        $replace = array(self::$processes[$type], '', '');
        $command = str_replace($search, $replace, self::$cmd[$type]);
        return substr($command, 4);
    }
    /**
     * 启动指定的进程
     * @param type $type
     * @param type $conf
     */
    public static function start($type, $conf)
    {
        list($start, $num) = $conf;
        $i = 0;
        while($i < $num){
            $index = $start - $i;
            $search = array('{ ini_file }', '{ name }');
            $replace = array(self::$processes[$type], $index);
            $command = str_replace($search, $replace, self::$cmd[$type]);
            $prefix = str_replace(SERVER_PATH, '', substr($command, 4));
            $ret = 0;
            exec("ps aux | grep '$prefix' | grep -v 'grep' | wc -l", $ret);
            $count = (int)array_pop($ret);
            if($count){
                self::log('process '.$prefix.' already started, restart or kill it');
            }else{
                exec($command);
                self::log("start process {$type} {$num}");
            }
            ++$i;
        }
    }
    /**
     * 发信号量给进程, 停止进程, 不丢数据
     * @param type $type
     */
    public static function stop($type)
    {
        $search = array('{ ini_file }', '{ name }', SERVER_PATH);
        $replace = array(self::$processes[$type], '', '');
        $command = str_replace($search, $replace, self::$cmd[$type]);
        $prefix = substr($command, 4);
        exec("ps aux | grep '$prefix' | grep -v 'grep' | awk '{print $2}' | xargs kill -s SIGTERM");
    }
    /**
     * 优雅重启
     * @param type $type
     * @param type $conf
     */
    public static function graceful($type, $conf)
    {
        $search = array('{ ini_file }', '{ name }', SERVER_PATH);
        $replace = array(self::$processes[$type], '', '');
        $command = str_replace($search, $replace, self::$cmd[$type]);
        $prefix = substr($command, 4);
        $ret = array();
        exec("ps aux | grep '$prefix' | grep -v 'grep' | awk '{print $2, \$NF}'", $ret);
        foreach($ret as $line){
            list($pid, $name) = explode(' ', $line);
            posix_kill($pid, SIGTERM);
        }
        list($start, $num) = $conf;
        $i = 0;
        while($i < $num){
            $index = $start - $i;
            $search = array('{ ini_file }', '{ name }');
            $replace = array(self::$processes[$type], $index);
            $command = str_replace($search, $replace, self::$cmd[$type]);
            exec($command);
            self::log("start process {$type} {$name}");
            ++$i;
        }
    }
    
    public static function restart($type, $conf)
    {
        self::graceful($type, $conf);
    }
    /**
     * 重载配置文件
     * @param type $type
     * @param type $conf
     */
    public static function reload($type, $conf)
    {
        $search = array('{ ini_file }', '{ name }', SERVER_PATH);
        $replace = array(self::$processes[$type], '', '');
        $command = str_replace($search, $replace, self::$cmd[$type]);
        $prefix = substr($command, 4);
        exec("ps aux | grep '$prefix' | grep -v 'grep' | awk '{print $2}' | xargs kill -s SIGUSR1");
        self::start($type, $conf);
    }
    
    public static function log($msg)
    {
        echo date('Y-m-d H:i:s ') . $msg . "\n";
    }

    public static function run($cmd)
    {
        $config = self::getConf();
        foreach($config as $type => $item){
            self::$cmd($type, $item);
        }
    }
}
if($argc < 2){
    echo "Usage: php " . $argv[0] . " start|stop|restart|graceful|reload\n\r";
    exit();
}
$cmd = $argv[1];
Commander::run($cmd);