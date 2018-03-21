<?php
namespace DF\Base;

abstract class Daemon
{
    const SIG_NONE = 0;
    const SIG_STOP = 1;
    const SIG_RESTART = 2;
    const SIG_RELOAD = 3;
    
    const U_SECOND = 50000;
    
    protected $conf;
    protected $output;
    protected $errorHandle;
    protected $cmd;
    protected $terminate = 0;
    protected $curMsg = '';
    protected $msg = array();
    
    protected static $signals = array(SIGINT, SIGQUIT, SIGTERM, SIGUSR1, SIGUSR2);
    
    function __construct()
    {
        global $argv;
        $arr = $argv;
        $index = array_pop($arr);
        $type = array_pop($arr);
        if(substr($type, -4) == '.php'){
            if(false === ($pos = strrpos($type, '/'))){
                $type = substr($type, 0, -4);
            }else{
                $type = substr($type, strrpos($type, '/') + 1, -4);
            }
            $this->name = $type.$index;
        }else{
            $this->name = $index;
        }
        $this->init($type, $index);
    }
    
    function __destruct()
    {
        if(!empty($this->output)){
            $this->error(json_encode(error_get_last()));
            fclose($this->output);
            $this->output = null;
        }
    }
    
    function signalHandler($signo)
    {
        $this->error("signal $signo");
        switch ($signo) {
            //用户自定义信号  
            case SIGUSR1: //重载配置
                $this->terminate = self::SIG_RELOAD;
                break;
            case SIGUSR2: //重启
                $this->terminate = self::SIG_RESTART;
                break;
            //中断进程  
            case SIGINT:
            case SIGQUIT:
            case SIGTERM:
                $this->terminate = self::SIG_STOP;
                break;
            default:
                return false;
        }
    }
    
    function log($message)
    {
        fwrite($this->output, \DF\Util\Helper::date()."\t[".ENV."]\t".$message . "\n");
    }
    
    function error($message)
    {
        fwrite($this->errorHandle, \DF\Util\Helper::date()."\t[".ENV."]\t".$message . "\n");
    }

    function start()
    {
        umask(0); //把文件掩码清0  
        if (pcntl_fork() != 0){ //是父进程，父进程退出  
            exit();  
        }  
        posix_setsid();//设置新会话组长，脱离终端  
        if (pcntl_fork() != 0){ //是第一子进程，结束第一子进程     
            exit();  
        }
        
        fclose(STDIN);  
        fclose(STDOUT);  
        fclose(STDERR);
        global $STDIN, $STDOUT, $STDERR;
        // Initialize new standard I/O descriptors
        $filename = LOG_PATH . DIRECTORY_SEPARATOR . "{$this->name}.log";
        $this->output = fopen($filename, 'a');
        $this->errorHandle = fopen(LOG_PATH . DIRECTORY_SEPARATOR . $this->name .'.error', 'a');
        $STDIN  = fopen('/dev/null', 'r'); // STDIN
        $STDOUT = $this->output; // STDOUT
        $STDERR = $this->errorHandle; // STDERR
        $this->installSignal();
        if (function_exists('gc_enable')){
            gc_enable();
        }
        register_shutdown_function(array($this, 'onFatalError'));
        set_error_handler(array($this, 'errorHandler'));
    }
    
    function installSignal()
    {
        foreach(self::$signals as $signal){
            pcntl_signal($signal, array($this, "signalHandler"));
        }
    }
    
    function createPid()
    {
        if(!defined('PID_PATH') || empty(PID_PATH)){
            return;
        }
        $filename = PID_PATH . DIRECTORY_SEPARATOR . "{$this->name}.pid";
        file_put_contents($filename, posix_getpid());
    }
    
    function errorHandler($errno, $errstr, $errfile, $errline, $errcontext)
    {
        try {
            throw new \Exception;
        } catch (\Exception $exc) {
            $errcontext = $exc->getTraceAsString();
            $str = sprintf("%s:%d\nerrcode:%d\t%s\n%s\n", $errfile, $errline, $errno, $errstr, $errcontext);
            $this->error($str);
        }
        return true;
    }
    
    function onFatalError()
    {
        if($this->terminate){
            return 0;
        }
        $this->terminate = self::SIG_STOP;
        $this->error("fatal error reload...");
        $this->restart();
        $error = error_get_last();
        $errorLog = \DF\Util\Helper::date()."\t".gethostname()."\t".$this->name."\t".\DF\Util\Helper::encode($this->curMsg, 'json')."<br/>".json_encode($error, JSON_PARTIAL_OUTPUT_ON_ERROR)."\n";
        file_put_contents(LOG_PATH . 'fatal_error.log', $errorLog, LOCK_EX | FILE_APPEND);
        if(isset($this->curMsg) && $this->curMsg){
            $this->error('Error Message: '. \DF\Util\Helper::encode($this->curMsg, 'json'));
        }
    }
    
    function quit()
    {
        $filename = $this->pidDir . DIRECTORY_SEPARATOR . "{$this->name}.pid";
        if(!empty($this->output)){
            fclose($this->output);
        }
        posix_kill(0, SIGKILL);
        unlink($filename);
    }
    
    function run()
    {
        $this->start();
        $this->createPid();
        $this->afterStart();//abstract
        $this->process();//abstract
        $this->afterStop();
    }
    
    abstract function init($type, $index);
    abstract function restart();
    abstract function afterStart();
    abstract function reload();
    abstract function process();
    abstract function afterStop();
}