<?php
namespace DF\Queue;

class PushTable
{
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
    
    public static function process($data)
    {
        self::gen(1, $data);
        self::gen(2, $data);
    }

    public static function getColumn($scope, $type)
    {
        return isset(self::$scope[$scope][$type]) ? self::$scope[$scope][$type] : 0;
    }
    
    public static function gen($scope, $data)
    {
        if(empty($data) || !isset($data['type'])){
            \DF\Base\Log::error('message format not correct');
            return;
        }
        $arr = array();
        $index = 0;
        $id = intval(microtime(true) * 1000000);
        foreach($data['emInfo'] as $info){
            $kind = self::getColumn($scope, $data['type']);
            if(empty($kind)){
                continue;
            }
            if(empty($info['warehouseId'])){
                continue;
            }
            if(in_array($data['type'], [87, 96]) && $info['num'] == 0){
                continue;
            }
            $arr[] = array(
                'id' => $index + $id, 
                'scope' => $scope,
                'type' => $data['type'],
                'kind' => abs($kind),
                'expenseId' => $info['expenseId'],
                'shopId' => $info['shopId'],
                'dt' => $data['customTime'],
                'num' => $info['num'],
                'totalPrice' => $kind > 0 ? abs($info['totalPrice']) : -abs($info['totalPrice']),
                'warehouseId' => $info['warehouseId'],
                'materialId' => $info['materialId'],
            );
            $shopIds[] = $info['shopId'];
            $materialIds[] = $info['materialId'];
            ++$index;
        }
        if(empty($arr)){
            return;
        }
        $groupShopIds = \DF\Component\Shop\ShopsComp::getGroupShopId($shopIds);
        $materials = \DF\Component\Shop\MeterialComp::getMaterial($materialIds);
        $toSearch = array();
        foreach($arr as $i => &$line){
            $line['groupShopId'] = isset($groupShopIds[$line['shopId']]) ? $groupShopIds[$line['shopId']] : 0;
            $line['groupMaterialId'] = isset($materials[$line['materialId']]['groupMaterialId']) ? $materials[$line['materialId']]['groupMaterialId'] : 0;
            unset($line['materialId']);
            $toSearch[$line['groupShopId']][$line['expenseId']][$line['groupMaterialId']] = $line['warehouseId'];
        }
        unset($line);
        $gmIds = array_column($arr, 'groupMaterialId');
        $gmaterials = \DF\Component\Shop\MeterialComp::getMaterial($gmIds);
        foreach($arr as $i => &$line){
            $line['category'] = isset($gmaterials[$line['groupMaterialId']]['firstCategory']) ? $gmaterials[$line['groupMaterialId']]['firstCategory'] : 0;
            $line['subcategory'] = isset($gmaterials[$line['groupMaterialId']]['secondCategory']) ? $gmaterials[$line['groupMaterialId']]['secondCategory'] : 0;
        }
        $toDelete = array();
        foreach($toSearch as $gId => $to){
            $map = \DF\Component\Sheet\AccumulateReportComp::getByExpenseId($gId, array_keys($to), $scope);
            foreach($map as $id => $tmp){
                //单据里删除了对应的物料ID
                if(!isset($toSearch[$gId][$tmp['expenseId']][$tmp['groupMaterialId']])){
                    $toDelete[$gId][] = $id;
                    continue;
                }
                //仓库改了，删除
                if($toSearch[$gId][$tmp['expenseId']][$tmp['groupMaterialId']] != $tmp['warehouseId']){
                    $toDelete[$gId][] = $id;
                    continue;
                }
            }
        }
        $update = array(
            'dt', 'shopId', 'num', 'totalPrice', 'warehouseId',
        );
        \DF\Component\Sheet\AccumulateReportComp::addUpdate($arr, $update);
        foreach($toDelete as $gId => $tmp){
            \DF\Component\Sheet\AccumulateReportComp::delete($gId, $tmp);
        }
    }
}