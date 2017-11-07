<?php

namespace DF\Controller\Sale;

class Index extends \DF\Base\Controller
{
    public function run()
    {
        $this->output(__FUNCTION__);
    }
    
    public function testAction()
    {
        $this->output(__FUNCTION__);
    }
    
    public function redisAction()
    {
        $redis = \DF\Base\Redis::getInstance(\DF\Base\Key::REIDS_TEST);
        $info = $redis->info();
        $this->output($info);
    }
    
    public function mysqlAction()
    {
        $info = \DF\Data\User::getData();
        $this->output($info);
    }
    
    public function goodsAction()
    {
        $info = \DF\Data\Goods::getData(['id' => 2]);
        $this->output($info);
    }
    
    public function lpushAction()
    {
        $redis = \DF\Base\Redis::getInstance(\DF\Base\Key::REIDS_TEST);
        $key = 'hello';
        $redis->lpush($key, 'world', 1, 2, "haha");
        call_user_func_array(array($redis, 'lpush'), array($key, 'first', 'second', 'third'));
        $info = $redis->lrange('hello', 0, 100);
        $this->output($info);
    }
}
