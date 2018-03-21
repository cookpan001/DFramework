<?php

/**
 * Description of import_report_data
 *
 * @author pzhu
 */
class ImportReportData
{
    const BATCH = 100;
    const TIMEOUT = 30;
    const SLEEP_S = 1;
    
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
    
    public function __construct()
    {
        $this->startTime = microtime(true);
    }
    
    public static function getColumn($scope, $type)
    {
        return isset(self::$scope[$scope][$type]) ? self::$scope[$scope][$type] : 0;
    }
    
    public function proceed()
    {
        $redis = \DF\Base\Redis::getInstance(\DF\Base\Setting::REDIS_MAIN);
        $id = $redis->incrby(\DF\Base\Key::IMPORT_TO_ACCUMULATE, self::BATCH);
        if(empty($id)){
            \DF\Base\Log::import("get id failed");
            return;
        }
        $data = array();
        $arr = \DF\Component\Shop\ExpenseMaterialComp::importExpenseMaterial($id - self::BATCH, self::BATCH);
        if(empty($arr)){
            $redis->incrby(\DF\Base\Key::IMPORT_TO_ACCUMULATE, -self::BATCH);
            \DF\Base\Log::import("no new data found", $id);
            exit;
        }
        foreach(self::$scope as $s => $detail){
            foreach($arr as $line){
                $column = isset($detail[$line['type']]) ? $detail[$line['type']] : 0;
                if(empty($column)){
                    continue;
                }
                $line['scope'] = $s;
                $line['kind'] = abs($column);
                if($column < 0){
                    $line['totalPrice'] = -$line['totalPrice'];
                }
                $data[] = $line;
            }
        }
        if(empty($data)){
            \DF\Base\Log::import("no data to import", $id);
            return;
        }
        \DF\Component\Sheet\AccumulateReportComp::add($data);
        return array(count($data), $id);
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
            \DF\Base\Log::import($i, $count, $id);
            $t2 = microtime(true);
            if($t2 - $this->startTime > self::TIMEOUT){
                \DF\Base\Log::import("run out of time", $t2 - $this->startTime);
                break;
            }
            ++$i;
            sleep(self::SLEEP_S);
        }
    }
}
include dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'base.php';
$app = new ImportReportData();
$app->run();