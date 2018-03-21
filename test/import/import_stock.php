<?php
ini_set('memory_limit', '512M');
include dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'base.php';

class ImportStock
{
    private $shopId = 0;
    private $limit = 0;
    
    public function __construct($shopId, $limit = 2000)
    {
        $this->shopId = $shopId;
        $this->limit = $limit;
        $this->script = <<<LUA
        local key = KEYS[1]
        local arr = cmsgpack.unpack(ARGV[1])
        for k, v in pairs(arr) do
            redis.call('hsetnx', key, k, v)
        end
LUA;
    }
    
    public function getExpense($id = 0)
    {
        $where = array(
            'id>'.$id,
            'shopId' => $this->shopId,
            'processStatus' => \DF\Base\Setting::FINISH_STATUS,
            'deletedAt is null'
        );
        $fields = array('id', 'type');
        $option = array('id' => 'id', 'value' => 'type', 'orderby' => 'id', 'limit' => 50);
        return \DF\Data\Shop\ExpenseData::getData($where, $option, $fields);
    }
    
    public function fromDatabase()
    {
        $redis = \DF\Base\Redis::getInstance(\DF\Base\Key::REDIS_MAIN);
        $detailKey = sprintf(\DF\Base\Key::SHOP_EXPENSE_MATERIAL, $this->shopId);
        $expenseKey = sprintf(\DF\Base\Key::SHOP_EXPENSE, $this->shopId);
        $redis->del($expenseKey);
        $redis->del($detailKey);
        $id = 0;
        $count = 0;
        $t1 = microtime(true);
        $redis->hset(\DF\Base\Key::VIP_SHOP_OPEN, $this->shopId, 1);
        while(true){
            $expenses = $this->getExpense($id);
            if(empty($expenses)){
                echo date('Y-m-d H:i:s')." expense finished, time used:".((microtime(true) - $t1) * 1000)."\n";
                return;
            }
            $eids = array_keys($expenses);
            $where = array(
                'expenseId' => $eids,
                'shopId' => $this->shopId,
                'deletedAt is null',
            );
            $data = \DF\Data\Shop\ExpenseMaterialData::getData($where, array(), ['id','warehouseId','materialId', 'endingStock', 'endingPrice', 'endingTotalPrice']);
            if(empty($data)){
                echo date('Y-m-d H:i:s')." expense_material finished\n";
                break;
            }
            $id = max($eids);
            $redis->hmset($expenseKey, $expenses);
            $m1 = microtime(true);
            $arr = array();
            foreach ($data as $line){
                $str = gzdeflate(json_encode([
                    (int)$line['materialId'],
                    (int)$line['warehouseId'],
                    (string)$line['endingStock'],
                    (string)$line['endingPrice'],
                    (string)$line['endingTotalPrice'],
                ]));
                $arr[$line['id']] = $str;
                ++$count;
            }
            $redis->eval($this->script, 1, $detailKey, \DF\Util\Helper::encode($arr));
            $m2 = microtime(true);
            echo "gzjson : $id, ".(($m2 - $m1) * 1000)."\n";
        }
        $t2 = microtime(true);
        echo "time used: ".(($t2 - $t1) * 1000)."\n";
    }
    
    public function run()
    {
        $this->fromDatabase();
    }
}
if($argc < 2){
    exit("Usage: php ".__FILE__." <shopId>\n");
}
$app = new ImportStock($argv[1]);
$app->run();