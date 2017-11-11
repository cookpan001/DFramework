<?php

//$sql = "insert      into    user('name') values('zp');";
//$sql = "update user set a=1 where b=2;";


//$insert = preg_match('#INSERT\s+(?:IGNORE\s+)?INTO\s+(\w+)#sim', $sql, $matches);
//$update = preg_match('#(DELETE|UPDATE)\s+(\w+)#sim', $sql, $matches);
//$update = preg_match('#SELECT\s+.*\s+FROM\s+\`?(\w+)\`?#sim', $sql, $matches);
//
//var_dump($matches);

function select()
{
    $sql = "select `id`, `name` from `user`;";
    preg_match('#(?:SELECT|DELETE)\s+.*\s+FROM\s+\`?(\w+)\`?#sim', $sql, $matches);
    var_dump($matches);
}

function insert()
{
    $sql = "insert      into    user('name') values('zp');";
    preg_match('#INSERT\s+(?:IGNORE\s+)?INTO\s+(\w+)#sim', $sql, $matches);
    var_dump($matches);
}

function update()
{
    $sql = "update user set a=1 where b=2;";
    preg_match('#UPDATE\s+(\w+)#sim', $sql, $matches);
    var_dump($matches);
}

function multi()
{
    $matches = array();
    
    $pattern = '#(?:(?:INSERT (?:[IGNORE\s+]*INTO))|UPDATE|(?:DELETE\s+FROM)|(?:SELECT\s+.*\s+FROM))\s+\`?(\w+)\`?#sim';
    //$pattern = '#((INSERT ([IGNORE\s+]*INTO))|UPDATE|(DELETE\s+FROM)|(SELECT\s+.*\s+FROM))\s+\`?(\w+)\`?#sim';
    $sql = "insert      into    `user`('name') values('zp');";
    preg_match($pattern, $sql, $matches);
    var_dump($matches);
    $sql = "update user set a=1 where b=2;";
    preg_match($pattern, $sql, $matches);
    var_dump($matches);
    $sql = "select `id`, `name` from `user`;";
    preg_match($pattern, $sql, $matches);
    var_dump($matches);
}

function where()
{
    $matches = array();
    $pattern = '#WHERE\s+(?:AND\s+)*\`?(\w+)\`?\s*=\s*\'?(\w+)\'?#sim';
    //$pattern = '#((INSERT ([IGNORE\s+]*INTO))|UPDATE|(DELETE\s+FROM)|(SELECT\s+.*\s+FROM))\s+\`?(\w+)\`?#sim';
    $sql = "insert      into    `user`('id','name') values(1, 'zp');";
    preg_match_all($pattern, $sql, $matches);
    var_dump($matches);
    $sql = "update user set a=1 where cc=11 and bb=222;";
    preg_match_all($pattern, $sql, $matches);
    var_dump($matches);
    $sql = "select `id`, `name` from `user` where c=3;";
    preg_match_all($pattern, $sql, $matches);
    var_dump($matches);
}
//where();
function lrange()
{
    $redis = new \Redis();
    $redis->connect('127.0.0.1', 6379);
    $key = 'hello';
    $count = 3;
    $script = <<<LUA
    local key = KEYS[1]
    local count = tonumber(ARGV[1])
    local ret = {}
    local i = 1
    while i < count do
        ret[#ret + 1] = redis.call('lpop', key)
        i = i + 1
    end
    return ret
LUA;
    $ret = $redis->eval($script, array($key, $count)/*keys和argv,先写key*/, 1/*前一个数组中key的数量*/);
    var_dump($ret);
}
function gen() {
    yield 'foo';
    yield 'bar';
    yield 'bar2';
}
 
$gen = gen();
var_dump($gen->send('something'));
var_dump($gen->send('something'));
var_dump($gen->send('something'));