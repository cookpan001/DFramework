<?php
namespace DF\Admin\Setting;

trait Menu{
    public static $menus = array(
        'Index' => 'index',
        '<font color=green>基本配置</font>',
        'Shop Config' => 'ShopAdmin',
        'Gray Config' => 'GrayAdmin',
    );
    
    public static $module = array(
        
    );
    
    public static $actions = array(
        'add', 'update', 'upload', 'download',
    );
    
    public static $adminOnly = array(
        'DomainAdmin' => array('env' => array('aws')),
    );
}

