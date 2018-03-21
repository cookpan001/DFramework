<?php
namespace DF\Queue;

/**
 * 更新数据表
 *  $data = [表名，要更改的字段及数值，where条件];
 *  $str = gzdeflat(json_encode($data));
 *  $redis->rpush('UpdateTable', $str);
 * @author pzhu
 */
class UpdateTable
{
    private static $daoMap = null;

    public static function process($data)
    {
        if(is_null(self::$daoMap)){
            self::$daoMap = json_decode(file_get_contents(COMMON_PATH . 'daomap.json'), true);
        }
        list($table, $vals, $where) = $data;
        if(!isset(self::$daoMap[$table])){
            return;
        }
        $classname = self::$daoMap[$table];
        if(!class_exists($classname)){
            return;
        }
        $classname::updateData($vals, $where);
    }
}