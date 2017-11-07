<?php

ini_set('display_errors', 'On');
error_reporting(E_ALL);
define('ROOT_NAMESPACE', 'DF');
define('ROOT_PATH', dirname(__FILE__) . DIRECTORY_SEPARATOR);
define('INCLUDE_PATH', ROOT_PATH . 'include' . DIRECTORY_SEPARATOR);
define('CONFIG_PATH', ROOT_PATH . 'config' . DIRECTORY_SEPARATOR);
include INCLUDE_PATH . 'autoload.php';