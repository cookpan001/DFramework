<?php

ini_set('display_errors', 'On');
error_reporting(E_ALL);
define('ROOT_NAMESPACE', 'DF');
define('ROOT_PATH', dirname(__FILE__) . DIRECTORY_SEPARATOR);
define('INCLUDE_PATH', ROOT_PATH . 'include' . DIRECTORY_SEPARATOR);
define('CONFIG_PATH', ROOT_PATH . 'config' . DIRECTORY_SEPARATOR);

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

include INCLUDE_PATH . 'autoload.php';