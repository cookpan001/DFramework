<?php
namespace DF\Cron;
/**
 * Description of FixReportDataCron
 *
 * @author pzhu
 */
use DF\Base\Cron;

class FixReportDataCron extends Cron
{
    const SLEEP_S = 1;
    
    public $timeout = 10;
    public $batch = 100;
    
    private static $scope = array(
        1 => array(
            //+采购
            9  => 1,
            13 => 1,
            49 => 1,
            50 => 1,
            52 => 1,
            30 => 1,
            //-退货
            14 => -1,
            15 => -1,
            16 => -1,
            47 => -1,
            //消耗
            78 => 2,
            88 => 2,
            92 => 2,
            87 => 2,
            96 => 2,
        ),
        2 => array(
            //入库
            9 => 1,
            13 => 1,
            45 => 1,
            12 => 1,
            30 => 1,
            40 => 1,
            42 => 1,
            43 => 1,
            46 => 1,
            90 => 1,
            94 => 1,
            55 => 1,
            72 => 1,
            79 => 1,
            49 => 1,
            50 => 1,
            52 => 1,
            58 => 1,
            77 => 1,
            //出库
            25 => 2,
            31 => 2,
            44 => 2,
            95 => 2,
            20 => 2,
            56 => 2,
            48 => 2,
            51 => 2,
            57 => 2,
            76 => 2,
            89 => 2,
            93 => 2,
            16 => 2,
            80 => 2,
            47 => 2,
            //消耗
            87 => 3,
            88 => 3,
            92 => 3,
            96 => 3,
            78 => 3,
        ),
    );
    
    public static function getColumn($scope, $type)
    {
        return isset(self::$scope[$scope][$type]) ? self::$scope[$scope][$type] : 0;
    }
    
    public function proceed()
    {
        $redis = \DF\Base\Redis::getInstance(\DF\Base\Setting::REDIS_MAIN);
        $id = $redis->incrby(\DF\Base\Key::FIX_ACCUMULATE, $this->batch);
        
        $groupShopId = \DF\Component\Shop\ShopsComp::getGroupShopId($this->shopId);
        $firstDay = strtotime(date('Ym',strtotime($this->dt)).'01');
        $endDay = strtotime('+1month', $firstDay) - 86400;
        $where[0] = 'dt>='.$firstDay;
        $where[1] = 'dt<='.$endDay;
        $where[3] = 'warehouseId>0';
        $where['groupShopId'] = $groupShopId;
        $where['shopId'] = $this->shopId;
        $where['scope'] = $this->scope;
        $fields = array(
            'id', 'groupMaterialId', 'warehouseId', 'expenseId', 'num', 'totalPrice',
        );
        $data = \DF\Data\Sheet\AccumulateReportData::getData($where, array('id' => array('expenseId', 'groupMaterialId', 'warehouseId'), 'overwrite' => true), $fields);
        $expenseIds = array_keys($data);
        //$expenseInfo = \DF\Data\Shop\ExpenseData::getKv(['expenseId' => $expenseIds], 'id', 'type');
        $emData = \DF\Data\Shop\ExpenseMaterialData::getData(['expenseId' => $expenseIds, 'status' => 1], [], ['expenseId', 'materialId', 'warehouseId', 'excludeTaxTotal', 'materialRealCount']);
        $check = array();
        foreach($emData as $line){
            $gmInfo = \DF\Component\Shop\MeterialComp::getMaterial($line['materialId']);
            $check[$line['expenseId']][$gmInfo['groupMaterialId']][$line['warehouseId']] = 1;
            if(!isset($data[$line['expenseId']][$gmInfo['groupMaterialId']][$line['warehouseId']])){
                echo "expenseId: {$line['expenseId']}, materialId: {$line['materialId']}, warehouseId: {$line['warehouseId']}, not exists in k_accumulate_report\n";
                continue;
            }
            $accData = $data[$line['expenseId']][$gmInfo['groupMaterialId']][$line['warehouseId']];
            if($accData['num'] != $line['materialRealCount']){
                echo "expenseId: {$line['expenseId']}, materialId: {$line['materialId']}, warehouseId: {$line['warehouseId']}, num: {$accData['num']}, materialRealCount: {$line['materialRealCount']}\n";
                continue;
            }
            if($accData['totalPrice'] != $line['excludeTaxTotal']){
                if(!isset($line['excludeTaxTotal'])){
                    echo json_encode($line) . "\n";exit;
                }
                echo "expenseId: {$line['expenseId']}, materialId: {$line['materialId']}, warehouseId: {$line['warehouseId']}, totalPrice: {$accData['totalPrice']}, excludeTaxTotal: {$line['excludeTaxTotal']}\n";
                continue;
            }
            //echo "expenseId: {$line['expenseId']}, materialId: {$line['materialId']}, warehouseId: {$line['warehouseId']}, OK\n";
        }
        $toDelete = array();
        foreach($data as $expenseId => $exArr){
            foreach($exArr as $gId => $gArr){
                foreach($gArr as $warehouseId => $wArr){
                    if(!isset($check[$expenseId][$gId][$warehouseId])){
                        echo "expenseId: {$expenseId}, groupMateraiId: {$gId}, warehouseId: {$warehouseId}, not exists in expense_material\n";
                        $toDelete[] = $wArr['id'];
                        continue;
                    }
                }
            }
        }
        echo "id in k_accumulate_report to delete: ".json_encode($toDelete) . "\n";
    }
    
    public function run()
    {
        $i = 0;
        while(true){
            $c = $this->proceed();
            if(is_null($c)){
                continue;
            }
            list($count, $id) = $c;
            $this->log($i, $count, $id);
            $t2 = microtime(true);
            if($t2 - $this->startTime > $this->timeout){
                $this->log("run out of time", $t2 - $this->startTime);
                break;
            }
            ++$i;
            sleep(self::SLEEP_S);
        }
    }
}