<?php
namespace DF\Component\Shop;

/**
 * Description of ExpenseComp
 *
 * @author pzhu
 */
class ExpenseComp
{
    //获取单据信息
    public static function get($expenseIds)
    {
        if(empty($expenseIds)){
            return array();
        }
        $where = array(
            'id' => (array)$expenseIds,
            'processStatus' => array(5, 22, 25, 28, 31, 10, 11, 35),
            'deletedAt is null',
        );
        return \DF\Data\Shop\ExpenseData::getKv($where, 'id', array('type', 'customTime'));
    }
}
