<?php
namespace DF\Component\Shop;
/**
 * Description of MeterialComp
 *
 * @author pzhu
 */
class MeterialComp
{
    private static $storage = array();
    /**
     * 获取物料所属集团物料Id及分类信息
     * @param mixed $materialIds
     */
    public static function getMaterial($materialIds)
    {
        $toSearch = array();
        $ret = array();
        foreach((array)$materialIds as $sid){
            if(isset(self::$storage[$sid])){
                $ret[$sid] = self::$storage[$sid];
            }else{
                $toSearch[] = $sid;
            }
        }
        if(!empty($toSearch)){
            $where = array(
                'id' => (array)$materialIds,
            );
            $arr = \DF\Data\Shop\MaterialData::getKv($where, 'id', array('groupMaterialId', 'firstCategory', 'secondCategory'));
            foreach($arr as $sid => $line){
                if(empty($line['groupMaterialId'])){
                    $line['groupMaterialId'] = $sid;
                }
                self::$storage[$sid] = $line;
            }
        }
        if(!is_array($materialIds)){
            return self::$storage[$materialIds];
        }
        foreach($toSearch as $sid){
            $ret[$sid] = isset(self::$storage[$sid]) ? self::$storage[$sid] : $sid;
        }
        return $ret;
    }
}
