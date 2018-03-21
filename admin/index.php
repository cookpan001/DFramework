<?php
ini_set('display_errors', 'On');
error_reporting(E_ALL);
mb_internal_encoding('UTF-8');
include dirname(dirname(__FILE__)).'/base.php';
define('ADMIN_ENV', true);

class index extends \DF\Admin\BaseAdmin
{
    public static function outputContent()
    {
        
    }
}
index::run();