<?php
$m1 = microtime(true);
$n = 2;
$gen = function() use ($n){
    $num = $n;
    $ret = array();
    while($num){
        $send = (yield $num);
        $ret = array_merge($ret, (array)$send);
        --$num;
    }
    var_dump($ret);
};

$co = $gen();
$co->send([1,2,3]);
$co->send([4,5,6]);

var_dump(microtime(true) - $m1);