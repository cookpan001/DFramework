<?php

namespace DF\Controller\Sale\Index;

/**
 * Description of Redis
 *
 * @author pzhu
 */
class Redis extends \DF\Base\Controller
{
    public function run()
    {
        $redis = \DF\Base\Redis::getInstance(\DF\Base\Setting::REDIS_MAIN);
        $info = $redis->keys('*');
        $this->output($info);
    }
}
