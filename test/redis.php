<?php
include dirname(__DIR__) . DIRECTORY_SEPARATOR . 'base.php';

$redis = new \DF\Sys\MyRedis2('localhost', 6379, 3, 'ccapchex');
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
        return cmsgpack.pack(ret)
LUA;
while(true){
    //$ret = $redis->eval($script, 1, 'zp', 10);
    $info = $redis->rpop('zp');
    echo date('Y-m-d H:i:s')."\n";
    $redis->close();
    sleep(2);
    usleep(mt_rand(1, 5000));
}


//$ret = \DF\Base\Cron::jobList();
//var_dump($ret);