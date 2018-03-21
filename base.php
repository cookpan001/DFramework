<?php

ini_set('display_errors', 'On');
error_reporting(E_ALL);
define('ROOT_NAMESPACE', 'DF');
define('ROOT_PATH', dirname(__FILE__) . DIRECTORY_SEPARATOR);
define('ADMIN_PATH', ROOT_PATH . 'admin' . DIRECTORY_SEPARATOR);
define('INCLUDE_PATH', ROOT_PATH . 'include' . DIRECTORY_SEPARATOR);
define('BIN_PATH', ROOT_PATH . 'bin' . DIRECTORY_SEPARATOR);
define('SERVER_PATH', ROOT_PATH . 'server' . DIRECTORY_SEPARATOR);
define('COMMON_PATH', ROOT_PATH . 'common' . DIRECTORY_SEPARATOR);
define('CRON_PATH', INCLUDE_PATH . 'cron' . DIRECTORY_SEPARATOR);
$filepath = ROOT_PATH . 'env.property';
if(defined('APP_NAME')){
    if(file_exists(ROOT_PATH . 'env.' . APP_NAME . '.property')){
        $filepath = ROOT_PATH . 'env.' . APP_NAME . '.property';
    }
}
if(file_exists($filepath)){
    $content = trim(file_get_contents($filepath));
    if($content){
        if(!defined('ENV')){
            define('ENV', $content);
        }
        define('CONFIG_PATH', ROOT_PATH .'env' . DIRECTORY_SEPARATOR . ENV . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR);
        $json = json_decode(trim(file_get_contents(CONFIG_PATH .'path.json')), true);
        foreach($json as $k => $v){
            if(!empty($v)){
                define(strtoupper($k).'_PATH', $v . DIRECTORY_SEPARATOR);
            }else if(file_exists(ROOT_PATH . $k)){
                define(strtoupper($k).'_PATH', ROOT_PATH . $k . DIRECTORY_SEPARATOR);
            }
        }
    }
}

if(PHP_OS == 'Darwin'){
    define('EINVAL', 22);/* Invalid argument */
    define('EPIPE', 32);/* Broken pipe */
    define('EAGAIN', 35);/* Resource temporarily unavailable */
    define('EINPROGRESS', 36);/* Operation now in progress */
    define('EWOULDBLOCK', EAGAIN);/* Operation would block */
    define('EADDRINUSE', 48);/* Address already in use */
    define('ECONNRESET', 54);/* Connection reset by peer */
    define('ETIMEDOUT', 60);/* Connection timed out */
    define('ECONNREFUSED', 61);/* Connection refused */
}else if(PHP_OS == 'Linux'){
    define('EINVAL', 22);/* Invalid argument */
    define('EPIPE', 32);/* Broken pipe */
    define('EAGAIN', 11);/* Resource temporarily unavailable */
    define('EINPROGRESS', 115);/* Operation now in progress */
    define('EWOULDBLOCK', EAGAIN);/* Operation would block */
    define('EADDRINUSE', 98);/* Address already in use */
    define('ECONNRESET', 104);/* Connection reset by peer */
    define('ETIMEDOUT', 110);/* Connection timed out */
    define('ECONNREFUSED', 111);/* Connection refused */
}
if(file_exists(ROOT_PATH. 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php')){
    include ROOT_PATH. 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
}
include INCLUDE_PATH . 'autoload.php';

function print_env($return = false)
{
    $str = 'ENV: '.ENV . "\n";
    $str .= "Database Config: \n".json_encode(\DF\Base\Config::getDb(), JSON_PRETTY_PRINT) . "\n";
    $str .= "Redis Config: \n".json_encode(\DF\Base\Config::getRedis(), JSON_PRETTY_PRINT) . "\n";
    $str .= "Queue Config: \n".json_encode(\DF\Base\Config::getQueue(), JSON_PRETTY_PRINT) . "\n";
    $str .= "Tables Config: \n".json_encode(\DF\Base\Config::getTables(), JSON_PRETTY_PRINT) . "\n";
    $str .= "Cron List: \n".json_encode(\DF\Base\Cron::jobList(), JSON_PRETTY_PRINT) . "\n";
    if($return){
        return $str;
    }
    echo $str;
}