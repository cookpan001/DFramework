<?php
namespace DF\Admin;

include dirname(dirname(__FILE__)).'/base.php';

/**
 * Description of ShopAdmin
 *
 * @author pzhu
 */
class ShopAdmin extends BaseAdmin {

    public static function getSetting() {
        return array(
            'header' => 'Shop',
            'dao' => 'DF\Data\Shop\ShopsData',
            'versionize' => 0, //加入版本控制
            'primary' => array('id'),
            'readonly' => array('id'),
            'hint' => array(),
            'types' => array(
                
            ),
            'values' => array(
                
            ),
        );
    }
}

if (false === strpos(__FILE__, $_SERVER['SCRIPT_NAME'])) {
    return;
}
ShopAdmin::run();