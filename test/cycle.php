<?php

include dirname(__DIR__) . DIRECTORY_SEPARATOR . 'base.php';

$redis = new \DF\Sys\MyRedis();
$redis->auth('ccapchex');

$key = 'cycle';

$redis->zadd($key, 0, '123:1:123');
$redis->zadd($key, 0, '12:1:123');
$redis->zadd($key, 0, '1:1:123');
$redis->zadd($key, 0, '1:2:123');
$redis->zadd($key, 2017, '123:1:123');
$redis->zadd($key, 2017, '12:1:123');

//$ret = $redis->zrangebylex($key, '(1:', '+', 'limit', '0', '1');
$ret = $redis->zrangebylex($key, '12:', '+');
var_export($ret);