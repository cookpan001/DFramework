<?php
namespace DF\Component\Sheet;
/**
 * Description of AccumulateReportComp
 *
 * @author pzhu
 */
class AccumulateReportComp
{
    public static function getByExpenseId($groupShopId, $expenseId, $scope)
    {
        $where = array(
            'groupShopId' => $groupShopId,
            'expenseId' => $expenseId,
            'scope' => $scope,
        );
        return \DF\Data\Sheet\AccumulateReportData::getKv($where, 'id', array('expenseId', 'groupMaterialId', 'warehouseId'));
    }
    
    public static function addUpdate($data, $update)
    {
        $toUpdate = array();
        foreach($update as $k){
            $toUpdate[$k] = "VALUES($k)";
        }
        \DF\Data\Sheet\AccumulateReportData::addDuplicateData($data, $toUpdate);
    }
    
    public static function add($data)
    {
        \DF\Data\Sheet\AccumulateReportData::addData($data, false);
    }
    
    public static function delete($groupShopId, $ids)
    {
        $where = array(
            'groupShopId' => $groupShopId,
            'id' => $ids,
        );
        \DF\Data\Sheet\AccumulateReportData::deleteData($where);
    }
}
