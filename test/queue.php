<?php
include dirname(__DIR__) . DIRECTORY_SEPARATOR . 'base.php';

$redis = new \DF\Sys\MyRedis('localhost', 6379, 5, 'ccapchex');

$str = gzdeflate(json_encode([[1,2,3,4]]));

$ret = $redis->lpush('cpx_sendReportData', $str);
$ret = $redis->lpush('cpx_sendReportData', gzdeflate(json_encode([[5,6,7,8]])));
var_dump($ret);