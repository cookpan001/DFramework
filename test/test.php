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

function script()
{
    $redis = new \Redis();
    $redis->connect('127.0.0.1', 6379);
    $redis->auth('ccapchex');
    $key = 'queue';
    $count = 3;
    $script = <<<LUA
    local ret = {}
        local num = tonumber(ARGV[1])
        for i, k in pairs(KEYS) do
            local c = tonumber(redis.call('llen', k))
            if c > 0 then
                if not ret[k] then
                    ret[k] = {}
                end
                local i = 1
                while i <= num and i <= c do
                    ret[k][#ret[k] + 1] = redis.call('lpop', k)
                    i = i + 1
                end
            end
        end
        return cjson.encode(ret)
LUA;
    $ret = $redis->eval($script, array($key, $count)/*keys和argv,先写key*/, 1/*前一个数组中key的数量*/);
    var_dump($ret);
}

function msg()
{
    $str = '{"33702":{"customTime":1509465600,"type":13,"expenseSn":"ZR-2017-1101-003","emInfo":[{"expenseId":33702,"shopId":1907,"materialId":352785,"warehouseId":1927,"num":"4.000","totalPrice":"20.00"}]},"33730":{"customTime":1509465600,"type":13,"expenseSn":"ZR-2017-1101-004","emInfo":[{"expenseId":33730,"shopId":1907,"materialId":352785,"warehouseId":1927,"num":"3.000","totalPrice":"5.01"},{"expenseId":33730,"shopId":1907,"materialId":352789,"warehouseId":1926,"num":"4.000","totalPrice":"5.00"}]},"33229":{"customTime":1510243200,"type":13,"expenseSn":"ZR-2017-1110-002","emInfo":[{"expenseId":33229,"shopId":1907,"materialId":352785,"warehouseId":1927,"num":"44.000","totalPrice":"44.00"}]}}';
    $redis = new \Redis();
    $redis->connect('192.168.0.190', 6379);
    $redis->auth('ccapchex');
    $redis->rPush('sendReportData', gzdeflate($str));
}

$a = gen();
$a->send('hello');
$ret = $a->next();
var_dump(4 . ':'. $ret);
$a->send('world');