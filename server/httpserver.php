<?php
define('IN_SWOOLE', true);
define('APP_NAME', 'httpserver');
include dirname(__DIR__).DIRECTORY_SEPARATOR.'base.php';
$server = new swoole_http_server('0.0.0.0', 9501);
$server->set(
    array(
        'reactor_num' => 2,
        'worker_num' => 16,
        'max_request' => 5000,
        'max_conn' => 256,
        'dispatch_mode' => 2,
        'open_tcp_keepalive' => 1,
//        'open_length_check' => true,
//        'package_length_type' => 'N',
//        'package_length_offset' => 0,
//        'package_max_length' => 800000,
    )
);
$dispatcher = new \DF\Base\Dispatcher();
$server->on('request', function ($request, $response) use ($dispatcher){
    $_SERVER['request'] = $request;
    $_SERVER['response'] = $response;
    $dispatcher->run();
});

$server->start();
