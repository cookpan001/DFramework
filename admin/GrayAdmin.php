<?php
namespace DF\Admin;

include dirname(dirname(__FILE__)).'/base.php';

/**
 * Description of GrayAdmin
 *
 * @author pzhu
 */
class GrayAdmin extends BaseAdmin
{
    const REDIS_MAIN = 'main';
    
    public static function getSetting() {
        return array(
            'header' => 'Gray Config',
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
    
    public static function selectKV($name, $values, $useDefault = true)
    {
        $str = "$name: <select name='$name'>";
        if($useDefault){
            $str .= "<option value='0'>....</option>";
        }
        $valRaw = self::input($name);
        foreach($values as $key => $val){
            $selected = '';
            if($key == $valRaw){
                $selected = 'selected';
            }
            $str .= "<option value='{$key}' $selected>$val</option>";
        }
        return $str . '</select>';
    }
    //生成操作按钮
    public static function req($op, $arr = array())
    {
        $str = '';
        if(empty($arr)){
            return $str;
        }
        $str .= "<td><form method='POST'>";
        foreach($arr as $k => $v){
            $str .= "<input type='hidden' name='$k' value='{$v}'>";
        }
        $str .= "<input type='hidden' name='op' value='{$op}'>";
        $str .= "<input type='hidden' name='action' value='init'>";
        return $str."<input type='submit' value='$op' /></form></td>";
    }

    public static function table($arr, $ops = array())
    {
        $str = '<table>';
        foreach($arr as $k => $v){
            $line = '';
            if(!isset($arr[0])){
                array_unshift($v, $k);
            }
            $line .= implode('</td><td>', (array)$v);
            $str .= "<tr><td>$line</td>";
            foreach($ops as $op){
                $str .= self::req($op, $v);
            }
            $str .= "</tr>";
        }
        return $str.'</table>';
    }

    public static function initAjax()
    {
        $op = filter_input(INPUT_POST, 'op');
        if(empty($op)){
            $op = filter_input(INPUT_GET, 'op');
        }
        $str = '';
        switch ($op) {
            case 'on':
                $str .= self::onAction();
                break;
            case 'off':
                $str .= self::offAction();
                break;
            case 'list':
                $str .= self::listAction();
                break;
            case 'addProject':
                $str .= self::addProjectAction();
                break;
        }
        return $str;
    }
    
    public static function addProjectAction()
    {
        $redis = \DF\Base\Redis::getInstance(self::REDIS_MAIN);
        $project = filter_input(INPUT_POST, 'project');
        $redis->hset('projectInGray', $project, 1);
        return 'OK';
    }
    
    public static function onAction()
    {
        $redis = \DF\Base\Redis::getInstance(self::REDIS_MAIN);
        //$shopInGray = $redis->hgetall('shopInGray');
        $project = filter_input(INPUT_POST, 'project');
        $shopId = filter_input(INPUT_POST, 'shopId');
        if(empty($shopId)){
            $redis->hset('projectInGray', $project, 1);
        }else{
            $redis->hset('shopInGray', $project.':'.$shopId, 1);
        }
        return 'OK';
    }
    
    public static function offAction()
    {
        $redis = \DF\Base\Redis::getInstance(self::REDIS_MAIN);
        $project = filter_input(INPUT_POST, 'project');
        $shopId = filter_input(INPUT_POST, 'shopId');
        if(empty($shopId)){
            $redis->hdel('projectInGray', $project);
        }else{
            $redis->hdel('shopInGray', $project.':'.$shopId);
        }
        return 'OK';
    }
    
    public static function listAction()
    {
        $redis = \DF\Base\Redis::getInstance(self::REDIS_MAIN);
        $project = filter_input(INPUT_POST, 'project');
        $all = $redis->hgetall('shopInGray');
        $ret = array();
        foreach($all as $k => $v){
            if(false === strpos($k, ':')){
                continue;
            }
            list($p, $shopId) = explode(':', $k);
            if($p != $project){
                continue;
            }
            $ret[] = array('project' => $project, 'shopId' => $shopId, 'status' => $v);
        }
        $ops = array('on', 'off');
        return "项目 {$project} 灰度列表: <br/>".self::table($ret, $ops);
    }
    
    public static function outputContent()
    {
        $tip = static::outTip();
        try{
            $result = self::runAction(false);
        }  catch (Exception $e){
            $result = $e->getMessage().$e->getTraceAsString();
        }
        $redis = \DF\Base\Redis::getInstance(self::REDIS_MAIN);
        $projects = $redis->hgetall('projectInGray');
        $arr = array();
        foreach($projects as $k => $v){
            $arr[] = ['project' => $k, 'status' => $v];
        }
        $str = <<<EOS
                $tip
        <form action='?action=init' method='post' enctype='multipart/form-data'>
            1. <button type='submit' name='op' value='addProject'>添加项目(add)</button><br/>
                项目: <input type='text' name='project' value='' /> <br/>
                shopId: <input type='text' name='shopId' value='' /> <br/>
        </form><br/>
        <pre>$result</pre>
EOS;
        $ops = array('list', 'on', 'off');
        $str .= "灰度项目: <br/>".self::table($arr, $ops);
        return $str;
    }
}

if (false === strpos(__FILE__, $_SERVER['SCRIPT_NAME'])) {
    return;
}
GrayAdmin::run();