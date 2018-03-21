<?php
namespace DF\Component\Shop;

/**
 * Description of ExpenseMaterialComp
 *
 * @author pzhu
 */
class ExpenseMaterialComp
{
    /**
     * 从id=seq起，获取batch条数据信息
     * @param type $seq
     * @param type $batch
     * @return type
     */
    public static function getToImport($seq = 0, $batch = 100)
    {
        $fields = array(
            'expenseId', 'deletedAt', 'shopId', 'warehouseId', 'materialId', 'excludeTaxTotal as totalPrice', 'materialRealCount as num, endingStock'
        );
        //fix data
        $ids = array(
            
        );
        for($i = 0; $i < $batch; ++$i){
            $ids[] = $seq + $i;
        }
        $idWhere = array(
            'id' => $ids,
        );
        $data = \DF\Data\Shop\ExpenseMaterialData::getData($idWhere, array(), $fields);
        return $data;
    }
    
    public static function importExpenseMaterial($id = 0, $batch = 100)
    {
        $index = intval(microtime(true) * 1000000) + mt_rand(10, 200) * 1000;
        $data = \DF\Component\Shop\ExpenseMaterialComp::getToImport($id, $batch);
        if(empty($data)){
            return;
        }
        $materialIds = array_column($data, 'materialId');
        $shopIds = array_column($data, 'shopId');
        $expenseIds = array_column($data, 'expenseId');
        $groupShopIds = \DF\Component\Shop\ShopsComp::getGroupShopId($shopIds);
        $materials = \DF\Component\Shop\MeterialComp::getMaterial($materialIds);
        $expenses = \DF\Component\Shop\ExpenseComp::get($expenseIds);
        $toSearch = array();
        foreach($data as $i => &$line){
            $line['id'] = $index + $i;
            if(!isset($expenses[$line['expenseId']]) || !empty($line['deletedAt']) || empty($line['warehouseId'])){
                unset($data[$i]);
                unset($line);
                continue;
            }
            if(in_array($expenses[$line['expenseId']]['type'], [87, 96]) && empty($line['num']) && empty($line['endingStock'])){
                unset($data[$i]);
                unset($line);
                continue;
            }
            $line['type'] = $expenses[$line['expenseId']]['type'];
            $line['dt'] = $expenses[$line['expenseId']]['customTime'];
            $line['groupShopId'] = isset($groupShopIds[$line['shopId']]) ? $groupShopIds[$line['shopId']] : $line['shopId'];
            $line['groupMaterialId'] = isset($materials[$line['materialId']]['groupMaterialId']) ? $materials[$line['materialId']]['groupMaterialId'] : $line['materialId'];
            unset($line['materialId']);
            unset($line['deletedAt']);
            unset($line['endingStock']);
            $toSearch[$line['groupShopId']][$line['expenseId']][$line['groupMaterialId']] = $i;
        }
        unset($line);
        $gmIds = array_column($data, 'groupMaterialId');
        $gmaterials = \DF\Component\Shop\MeterialComp::getMaterial($gmIds);
        foreach($data as $i => &$line){
            $line['category'] = isset($gmaterials[$line['groupMaterialId']]['firstCategory']) ? $gmaterials[$line['groupMaterialId']]['firstCategory'] : 0;
            $line['subcategory'] = isset($gmaterials[$line['groupMaterialId']]['secondCategory']) ? $gmaterials[$line['groupMaterialId']]['secondCategory'] : 0;
        }
        return $data;
    }
}
