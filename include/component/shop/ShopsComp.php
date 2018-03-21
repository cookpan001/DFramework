<?php
namespace DF\Component\Shop;
/**
 * Description of ShopsComp
 *
 * @author pzhu
 */
class ShopsComp
{
    private static $storage = array();
    /**
     * 获取集团id
     * @param mixed $shopIds
     * @return type
     */
    public static function getGroupShopId($shopIds)
    {
        $toSearch = array();
        $ret = array();
        foreach((array)$shopIds as $sid){
            if(isset(self::$storage[$sid])){
                $ret[$sid] = self::$storage[$sid];
            }else{
                $toSearch[] = $sid;
            }
        }
        if(!empty($toSearch)){
            $where = array(
                'id' => (array)$shopIds,
            );
            $arr = \DF\Data\Shop\ShopsData::getKv($where, 'id', 'groupId');
            foreach($arr as $sid => $groupId){
                if(empty($groupId)){
                    self::$storage[$sid] = $sid;
                }else{
                    self::$storage[$sid] = $groupId;
                }
            }
        }
        if(!is_array($shopIds)){
            return self::$storage[$shopIds];
        }
        foreach($toSearch as $sid){
            $ret[$sid] = isset(self::$storage[$sid]) ? self::$storage[$sid] : $sid;
        }
        return $ret;
    }
}
