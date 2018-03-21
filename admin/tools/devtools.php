<?php
include dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'base.php';
class OtherTool
{
    const SECRET = 'ZG9uaJ3QgaGFjaw==djg730mv.f2jcncqpcmf=bjcxmnsd74,b8*';
    
    private $isAjax = false;
    function run()
    {
        if(empty($_REQUEST['rawOutput']))
        {
            echo '<textarea id="result">';
        }
        if (isset($_REQUEST['op']) && !empty($_REQUEST['op']))
        {
            $op = $_REQUEST['op'] . 'Action';
            if (!method_exists($this, $op))
            {
                echo "method not exists.<br/>";
                exit;
            }

            $ret = call_user_func_array(array($this, $op), array());
            if (is_array($ret) || is_object($ret) || is_resource($ret))
            {
                var_export($ret);
            } else
            {
                echo $ret;
            }
        }
        if(empty($_REQUEST['rawOutput']))
        {
            echo '</textarea>';
        }
    }

    function __construct()
    {
        if(isset($_REQUEST['dataType']))
        {
            $this->isAjax = true;
            call_user_func_array(array($this, $_REQUEST['op'].'Action'), array());
            exit;
        }
    }
    function getCode()
    {
        $historySize = 20;
        if(!isset($_SESSION['code']) || isset($_REQUEST['Clear']))
        {
            $_SESSION['code'] = array();
        }
        if(!isset($_REQUEST['code']))
        {
            $code = '';
            return $code;
        }
        $code = $_REQUEST['code'];
        $history = $_SESSION['code'];
        if(isset($_REQUEST['historyId']) && isset($history[$_REQUEST['historyId']]))
        {
            $code = $history[$_REQUEST['historyId']];
        }
        else
        {
            $history[time()] = $code;
        }
        if(count($history) > $historySize)
        {
            $tmp = array_chunk($history, $historySize);
            $history = array_pop($tmp);
            unset($tmp);
        }
        $_SESSION['code'] = $history;
        return $code;
    }

    function executeAction()
    {
        $code = $this->getCode();
        $host = $_REQUEST['host'];
        if(substr($host, 0, 4) != 'http'){
            $host = 'http://'. trim($host, '/');
        }
        if(!isset($_REQUEST['historyId']))
        {
            $now = time();
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => false,
                CURLOPT_TIMEOUT => 200,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_URL => $host . '/debug/eval/execute',
                CURLOPT_POSTFIELDS => array(
                    'code' => $code,
                    'seq' => $now,
                ),
                CURLOPT_HTTPHEADER => array(
                    'SECRET: '. md5(self::SECRET . $now),
                ),
            ));
            $ret = curl_exec($ch);
            $r = json_decode($ret, true);
            if($r){
                return var_export($r);
            }
            var_export($ret);
        }
    }

    function getShellCode()
    {
        if(isset($_REQUEST['shellCode']))
        {
            return trim($_REQUEST['shellCode']);
        }
        return '';
    }

    function shellAction()
    {
        $code = $this->getShellCode();
        if('' == $code)
        {
            echo "";
            exit;
        }
        $q = <<<EOS
            exec(\$code,\$r);return implode("\n",\$r);
EOS;
        $ret = eval($q);
        echo $ret;
    }
    
    function timestampAction()
    {
        $timestamp = $_REQUEST['timestamp'];
        $date = $_REQUEST['date'];
        if(empty($timestamp) && empty($date)){
            echo time()."\n";
            echo date('Y-m-d H:i:s P')."\n";
        }
        if(!empty($timestamp)){
            echo date('Y-m-d H:i:s P', $timestamp)."\n";
        }
        if(!empty($date)){
            echo strtotime($date);
        }
    }
    
    function jsonDiffAction()
    {
        $content1 = trim($_REQUEST['content1']);
        $content2 = trim($_REQUEST['content2']);
        echo Util_Tool::diffArray(json_decode($content1, true), json_decode($content2, true));
    }
    
    function base64decodeAction()
    {
        $content = trim($_REQUEST['content']);
        echo base64_decode($content);
    }
    
    public static function getRedisList()
    {
        $json = \DF\Base\Config::getRedis();
        $str = '<select name="redisType">';
        foreach($json as $type => $_v){
            $str .= "<option value='$type'>$type</option>";
        }
        return $str.'</select>';
    }
    
    function redisQueryAction()
    {
        $redisType = $_REQUEST['redisType'];
        $key = $_REQUEST['prefix'];
        if($redisType == 'other'){
            $redis = new Predis\Client($_REQUEST['conn']);
        }else{
            $redis = Base_Redis::getInstance($redisType);
        }
        $type = $redis->type($key);
        $ttl = $redis->ttl($key);
        switch ($type) {
            case 'string':
                $ret = $redis->get($key);
                break;
            case 'list':
                $ret = $redis->lrange($key,0,-1);
                break;
            case 'set':
                $ret = $redis->smembers($key);
                break;
            case 'zset':
                $ret = $redis->zrangebyscore($key,'-inf','+inf','withscores');
                break;
            case 'hash':
                $ret = $redis->hgetall($key);
                break;
            default:
                $ret = '';
                break;
        }
        if(isset($_REQUEST['resultType']) && !empty($_REQUEST['resultType'])){
            $resultType = $_REQUEST['resultType'];
            if(is_array($ret)){
                foreach($ret as $k => $value){
                    if($type == 'zset'){
                        if(is_string($k) && strlen($k) && ord($k[0]) > 127){
                            $result[] = Util_Tool::decode($k, $resultType);
                            $result[] = $value;
                        }else{
                            $result[] = array('value' => Util_Tool::decode($value[0], $resultType), 'score' => $value[1]);
                        }
                    }else if(is_string($k) && strlen($k) && ord($k[0]) > 127){
                        $result[] = Util_Tool::decode($k, $resultType);
                        $result[] = $value;
                    }else{
                        $result[$k] = Util_Tool::decode($value, $resultType);
                    }
                }
            }else{
                $result = Util_Tool::decode($ret, $resultType);
            }
        }else{
            $result = $ret;
        }
        echo "Key: $key\n";
        echo "Data type: $type\n";
        echo "Time to live: $ttl seconds\n";
        echo "Data: \n\n";
        var_export($result);
    }
    
    function getKeysAction()
    {
        $redisType = $_REQUEST['redisType'];
        $prefix = $_REQUEST['prefix'];
        if($redisType == 'other'){
            $redis = new Predis\Client($_REQUEST['conn']);
        }else{
            $redis = Base_Redis::getInstance($redisType);
        }
        $data = $redis->keys($prefix.'*');
        sort($data);
        echo json_encode($data);
    }
}
$app = new OtherTool();
?>
<html>
    <head>
        <title><?php echo defined('ENV') ? ENV . '-' : '';?>Tool</title>
        <meta http-equiv="content-type" content="text/hmtl; charset=utf-8"/>
        <meta name="viewport" content="width=device-width, initial-scale=1"/>
        <link rel="stylesheet" href="https://cdn.staticfile.org/codemirror/5.25.0/codemirror.min.css">
        <link rel="stylesheet" href="https://cdn.staticfile.org/codemirror/5.25.0/theme/eclipse.min.css">
        <style type="text/css">.CodeMirror {border-top: 1px solid black; border: 1px solid black; width: 600px; height: 80%;}</style>
        <script src="https://cdn.staticfile.org/codemirror/5.25.0/codemirror.min.js"></script>
        <script src="https://cdn.staticfile.org/codemirror/5.25.0/addon/edit/matchbrackets.min.js"></script>
        <script src="https://cdn.staticfile.org/codemirror/5.25.0/mode/clike/clike.min.js"></script>
        <script src="https://cdn.staticfile.org/codemirror/5.25.0/mode/php/php.min.js"></script>
        <style>
            .titleName{
                width: 100%;
                height: 50px;
                text-align: center;
                line-height: 50px;
                font-size: 20px;
                color: #3D9AD1;
            }
            #requestDev{
                float: left;
                width: 50%;
            }
            #resultDiv{
                width: 50%;
                margin-left: 50%;
                height: 90%;
                position: absolute;
            }
            #result{
                height: 95%;
            }
            #code_eval {
                width: 100%;
            }
            textarea{
                resize:none;
                outline:none;
                width: 86%;
                border: 1px solid #314055;
            }
            .CodeMirror{
                width: 85%;
                margin-left: 1%;
                border: 1px solid #314055;
            }
            .inputText{
                width: 82%;
                height: 30px;
                margin-left:10px;
                margin-bottom: 10px;
                border: 1px solid #314055;
            }
            .buttonStyle{
                width: 8%;
                height:3%;
                color: white;
                margin-top: 10px;
                margin-bottom: 10px;
                font-size: 30%;
                font-weight: bold;
                background-color: #3D9AD1;
                border: 1px solid #314055;
            }
            select{
                width: 30%;
                height: 30px;
                margin-bottom: 10px;
                border: 1px solid #314055;
                font-size: 18px;
            }
            input[type="radio"] {
                width: 20px;
                height: 20px;
            }
        </style>
    </head>
    <body>
        <div style="width:100%;">
            <div id="requestDev">
                <h3 class="titleName">PHP Code execute</h3>
                <form method="POST" action="?" enctype="multipart/form-data" id="code_eval">
                    <button class="buttonStyle" type="button" value="AjaxRun" onclick="javascript: devObject.fillResult('Receiving data ...');devObject.ajaxRun();" >AjaxRun</button><br/>
                    host: <input type="text" name="host" class="inputText"/>
                    <textarea id="code" name="code" rows="10" cols="80" style="font-size:13px;" ><?php echo $app->getCode(); ?></textarea><br />
                    <input type="hidden" name="op" value="execute" class="inputText">
                    <fieldset style="width: 83%;border: 1px solid #314055;">
                        <legend>
                            <a href="#code_history" onclick="javascript: $('#code_history').toggle();">History</a>
                        </legend>
                        <div id="code_history" style="display:none;">
                            <?php
                            if(!empty($_SESSION['code']))
                            {
                                $history = $_SESSION['code'];
                                foreach($history as $k => $line)
                                {
                                    if(empty($line))
                                    {
                                        continue;
                                    }
                                    $line = json_encode($line);
                                    echo "<a href='#' onclick='javascript: editCode($line);'>Edit</a>&nbsp;&nbsp;<font color=gray size=2>". date('Y-m-d H:i:s', $k);
                                    echo "</font>&nbsp;<span>".substr($line, 0, 60)." ...</span><br/>";
                                }
                            }
                            ?>
                            <button class="buttonStyle" type='button' onclick="javascript: $(this).after('<input type=\'hidden\' name=\'Clear\' value=1 />');$('#code_eval').submit();">Clear</button>
                        </div>
                    </fieldset>
                </form>
                <h3 class="titleName">base64 decode</h3>
                <form method="POST" action="?" enctype="multipart/form-data" id="base64decode">
                    <textarea name="content" rows="10" cols="80" style=" font-size:13px;" ></textarea><br/>
                    <button type="button" class="buttonStyle" value="AjaxRun" onclick="javascript: devObject.fillResult('Receiving data ...');devObject.base64decode();" >AjaxRun</button><br/>
                </form>
                <h3 class="titleName">timestamp parser</h3>
                <form method="POST" action="?" enctype="multipart/form-data" id="parseTime">
                    timestamp: <input type="text" name="timestamp" style="width: 70%;height: 30px;margin-bottom: 10px;"class="inputText"/><br/>
                    date: <input type="text" name="date" style="width: 70%;height: 30px;margin-left: 58px;"class="inputText"/><br/>
                    <button type="button" class="buttonStyle" value="AjaxRun" onclick="javascript: devObject.fillResult('Receiving data ...');devObject.ajaxTimestamp();" >AjaxRun</button><br/>
                </form>
                <h3 class="titleName" class="titleName">Redis Query</h3>
                <form method="POST" action="?" enctype="multipart/form-data" id="redisQuery">
                    redisType: <?php echo OtherTool::getRedisList(); ?><br />
                    conn: <input type="text" name="conn" value="" style="width: 70%;height: 30px;margin-bottom: 10px;" class="inputText"/><br/>
                    key: <input type="text" name="prefix" value="" style="width: 70%;height: 30px;margin-left: 20px;" class="inputText"/><br/>
                    <!-- <select name="key"></select><br/> -->
                    resultType: 
                    <input type="radio" name="resultType" value='' checked/>raw
                    <input type="radio" name="resultType" value='json' />json
                    <input type="radio" name="resultType" value='msgpack' />msgpack<br/>
                    <input type="hidden" name="op" value="redisQuery">
                    <!-- <button type="button" value="getKeys" onclick="devObject.getKeys();" >getKeys</button>&nbsp;&nbsp;&nbsp;&nbsp; -->
                    <button type="button" class="buttonStyle" value="delKey" onclick="devObject.delKey();" >delKey</button>&nbsp;&nbsp;&nbsp;&nbsp;
                    <button type="button" class="buttonStyle" value="AjaxRun" onclick="javascript: devObject.fillResult('Receiving data ...');devObject.ajaxRedis();" >Query</button><br/>
                </form>
            </div>
            <div id="resultDiv">
                <h3 class="titleName">Operation result</h3>
                <pre><?php $app->run(); ?></pre>
            </div>
        </div>
    </body>
    <script src="https://cdn.staticfile.org/jquery/3.2.1/jquery.min.js"></script>
    <script>
        var editor = CodeMirror.fromTextArea(document.getElementById("code"), {
            lineNumbers: true,
            matchBrackets: true,
            mode: "text/x-php",
            indentUnit: 4,
            indentWithTabs: true,
            theme: "eclipse"
        });
        var devObject = {
            'proto' : '<?php echo !empty($_REQUEST['proto']) ? $_REQUEST['proto'] : '0';?>',
            'rawOutput' : '<?php echo !empty($_REQUEST['rawOutput']) ? 1 : 0;?>',
            'requestProto' : '<?php echo !empty($_REQUEST['requestType']) ? $_REQUEST['requestType'] : '';?>',
            'responseProto' : '<?php echo !empty($_REQUEST['responseType']) ? $_REQUEST['responseType'] : '';?>',
            'host' : window.location.protocol+'//<?php echo $_SERVER["HTTP_HOST"];?>',
            'accountType' : '<?php echo isset($_REQUEST["user"]) ? $_REQUEST["user"] : 'userId';?>',
            'postData' : <?php echo json_encode($_POST);?>,
            'queryParse' : function(queryStr){
                var arr = queryStr.split("&");
                var ret = {};
                for(var i in arr){
                    var tmp = arr[i].split("=");
                    var key = decodeURIComponent(tmp[0]);
                    var value = decodeURIComponent(tmp[1]);
                    var parsed = devObject.strParse(key, value);
                    var left = key.indexOf('[');
                    if(left !== -1){
                        key = key.substring(0, left);
                    }
                    if(undefined === ret[key]){
                        ret[key] = [];
                    }
                    if(parsed instanceof Array){
                        if(undefined === ret[key]){
                            ret[key] = parsed;
                        }else{
                            ret[key].concat(parsed);
                        }
                    }else if(parsed instanceof Object){
                        for(var i in parsed){
                            ret[key][i] = parsed[i];
                        }
                    }else{
                        ret[key] = value;
                    }
                }
                return ret;
            },
            'strParse' : function(key, value){
                var left = key.indexOf('[');
                var right = key.lastIndexOf(']');
                if(left === -1 || right === -1){
                    return value;
                }
                if(left === right -1){
                    return new Array(value);
                }
                var subKey = key.substr(left + 1, right - left - 1);
                var obj = {};
                obj = {};
                var subValue = devObject.strParse(subKey, value);
                var subLeft = subKey.indexOf('[');
                if(subLeft === -1){
                    obj[subKey] = subValue;
                }else{
                    var subKey2 = subKey.substring(0,subLeft);
                    obj[subKey][subKey2] = subValue;
                }
                return obj;
            },
            'fillResult' : function(ret){
                $('#result').val(ret);
            },
            'ajaxProto' : function(){
                var obj = {'dataType':'json'};
                obj['proto'] = $("#parseProto input[name='proto']").val();
                obj['content'] = $("#parseProto textarea").val();
                obj['op'] = 'parseProto';
                $.post('?',obj,function(ret){
                    devObject.fillResult(ret);
                });
            },
            'jsonDiff' : function(){
                var obj = {'dataType':'json'};
                obj['content1'] = $("#jsonDiff textarea[name='content1']").val();
                obj['content2'] = $("#jsonDiff textarea[name='content2']").val();
                obj['op'] = 'jsonDiff';
                $.post('?',obj,function(ret){
                    devObject.fillResult(ret);
                });
            },
            'base64decode' : function(){
                var obj = {'dataType':'json'};
                obj['content'] = $("#base64decode textarea[name='content']").val();
                obj['op'] = 'base64decode';
                $.post('?',obj,function(ret){
                    devObject.fillResult(ret);
                });
            },
            'ajaxTimestamp' : function(){
                var obj = {'dataType':'json'};
                obj['timestamp'] = $("#parseTime input[name='timestamp']").val();
                obj['date'] = $("#parseTime input[name='date']").val();
                obj['op'] = 'timestamp';
                $.post('?',obj,function(ret){
                    devObject.fillResult(ret);
                });
            },
            'ajaxRun' : function(){
                var obj = {'dataType':'json'};
                $('#code_eval').find("input[name='op']").each(function(idx, item){
                    var val = $(item).val();
                    var name = $(item).attr('name');
                    obj[name] = val;
                });
                $('#code_eval').find("input[name='host']").each(function(idx, item){
                    var val = $(item).val();
                    var name = $(item).attr('name');
                    obj[name] = val;
                });
                $('#code_eval').find("textarea[name='code']").each(function(idx, item){
                    var val = editor.getValue();
                    var name = $(item).attr('name');
                    obj[name] = val;
                });
                $.post('?',obj,function(ret){
                    devObject.fillResult(ret);
                });
            },
            'ajaxShell' : function(){
                var q = $('#shell_eval').serialize()+'&dataType=json';
                $.post('?',q,function(ret){
                    devObject.fillResult(ret);
                });
            },
            'ajaxRedis' : function(){
                var resultType = $("#redisQuery input[name='resultType']:checked").val();
                var q = $('#redisQuery').serialize()+'&dataType=1&resultType='+resultType;
                $.post('?',q,function(ret){
                    devObject.fillResult(ret);
                });
            },
            'init' : function(){
                if(parseInt(devObject.rawOutput) > 0){
                    $("#requestAjax input[name='rawOutput']").attr('checked','checked');
                }
                if(parseInt(devObject.proto) > 0){
                    $("#requestAjax input[name='proto']").click();
                }
                $("select[name='user']").val(devObject.accountType);
            },
            'getKeys':function(){
                var redisType = $("#redisQuery select[name='redisType']").val();
                var prefix = $("#redisQuery input[name='prefix']").val();
                var conn = $("#redisQuery input[name='conn']").val();
                var resultType = $("#redisQuery input[name='resultType']:checked").val();
                $("#redisQuery select[name='key']").html('<option value=0>receiving...</option>');
                $.post('?',{"op":"getKeys",'dataType':resultType,'redisType':redisType,'prefix':prefix,'conn':conn},function(data){
                    var s = "";
                    for(var item in data){
                        s += "<option value='"+data[item]+"'>"+data[item]+"</option>";
                    }
                    $("#redisQuery select[name='key']").html(s);
                },'json');
            }
        };

        function editCode(line)
        {
            devObject.fillResult('Receiving data ...');
            $('#code_eval').find("textarea[name='code']").val(line);
            devObject.ajaxRun();
//            $('#code_eval').find("input[name='op']").each(function(idx, item){
//                $(item).val('execute');
//                $(item).after("<input type='hidden' name='historyId' value='"+line+"'/>");
//                $('#code_eval').submit();
//            });
        }

        function ajaxInterface()
        {
            var obj = {};
            obj['url'] = $("#requestAjax input[name='url']").val();
            obj['op'] = $("#requestAjax input[name='op']").val();
            obj['tvuid'] = $("#requestAjax input[name='tvuid']").val();
            obj['user'] = $("#requestAjax select[name='user']").val();
            obj['gver'] = $("#requestAjax input[name='gver']").val();
            obj['param'] = [];
            obj['value'] = [];
            $("#requestAjax input[name='param[]']").each(function(idx,item){
                obj['param'].push($(item).val());
            });
            $("#requestAjax input[name='value[]']").each(function(idx,item){
                obj['value'].push($(item).val());
            });
            obj['proto'] = $("#requestAjax input[name='proto']").attr("checked") === undefined ? 0 : 1;
            obj['rawOutput'] = $("#requestAjax input[name='rawOutput']").attr("checked") === undefined ? 0 : 1;
            if(obj['proto']){
                obj['requestType'] = $("#requestAjax select[name='requestType']").val();
                obj['responseType'] = $("#requestAjax select[name='responseType']").val();
            }
            processInterface(obj);
        }

        function editInterface(param)
        {
            var obj = devObject.queryParse(param);
            processInterface(obj);
        }

        function processInterface(obj)
        {
            devObject.uncheckProto();
            $("input[name='param[]']").parent().parent().remove();
            $("input[name='value[]']").parent().parent().remove();
            devObject.fillResult('Receiving data ...');
            $("input[name='url']").val(obj["url"]);
            if(obj["user"] === 'mobileId'){
                devObject.accountType = 'mobileId';
            }
            $("input[name='tvuid']").val(obj["tvuid"]);
            $("select[name='user']").val(obj["user"]);
            obj['dataType'] = 'json';
            devObject.proto = obj.hasOwnProperty('proto') ? obj['proto'] : 0;
            devObject.rawOutput = obj.hasOwnProperty('rawOutput') ? obj['rawOutput'] : 0;
            if(obj.hasOwnProperty('requestType')){
                devObject.requestProto = obj.hasOwnProperty('requestType') ? obj['requestType'] : '';
            }
            if(obj.hasOwnProperty('responseType')){
                devObject.responseProto = obj.hasOwnProperty('responseType') ? obj['responseType'] : '';
            }
            if(obj.hasOwnProperty('param')){
                for(var i in obj['param']){
                    append("input[name='tvuid']", obj['param'][i], obj['value'][i]);
                }
            }
            $.post('?', obj, function(ret){
                if(parseInt(devObject.rawOutput) > 0){
                    $("#requestAjax input[name='rawOutput']").attr('checked','checked');
                }
                if(parseInt(devObject.proto) > 0){
                    $("#requestAjax input[name='proto']").attr('checked','checked');
                    var jDom = $("#requestAjax input[name='proto']").parent().parent().prev();
                    $("#requestAjax input[name='proto']").attr('disabled', true);
                    devObject.checkProto(jDom);
                }
                $("select[name='user']").val(devObject.accountType);
                devObject.fillResult(ret);
            });
        }

        function append(item, key, value)
        {
            if(undefined === key){
                key = '';
            }
            if(undefined === value){
                value = '';
            }
            if(!$(item)){
                return;
            }
            var str = "<tr><td><input type='text' name='param[]' size=25 value='"+key+"'/></td>";
            str += "<td><input type='text' name='value[]' size='25' value='"+value+"'/>";
            //str += "&nbsp;&nbsp;<input type='text' name='subType[]' size='20' value='"+value+"'/>";
            str += "<a href='#ajax_interface' onclick='append(this);'>Add Param</a>&nbsp;&nbsp;";
            str += "<a href='#ajax_interface' onclick='del(this);'>Del Param</a>";
            str += "</td></tr>";
            $(item).parent().parent().after(str);
        }

        function del(item)
        {
            $(item).parent().parent().remove();
        }

        function gdsValidator()
        {
            $("#gdsDetail").parent().parent().empty();
            var gds = $("#GDSType").val();
            var obj = {'dataType':'json','GDSType':gds,'op':'gdsFields'};
            $.post('?',obj,function(ret){
                var table = '<tr><td colspan=2><table border=1 id="gdsDetail">';
                table += "<tr><td>FIELD</td><td>TYPE</td><td>REGEX</td><td>HINT</td></tr>";
                for(var i in ret[0]){
                    var field = ret[0][i];
                    table += "<tr>";
                    table += "<td>"+field+"</td>";
                    table += "<td><select name='type["+field+"]'><option value='INT'>INT</option><option value='STRING'>STRING</option><option value='DATETIME'>DATETIME</option></select></td>";
                    table += "<td><input type='text' name='regex["+field+"]' value='' /></td>";
                    table += "<td><input type='text' name='hint["+field+"]' value='' /></td>";
                    table += "</tr>";
                }
                table += '</table></td></tr>';
                $("#GDSType").parent().parent().parent().append(table);
                for(var j in ret[1]){
                    var field = ret[0][j];
                    var value = ret[1][j];
                    if(devObject.postData && devObject.postData['regex'] && devObject.postData['regex'][field]){
                        $("input[name='regex["+field+"]'").val(devObject.postData['regex'][field]);
                        $("input[name='hint["+field+"]'").val(devObject.postData['hint'][field]);
                    }
                    $("select[name='type["+field+"]']").val(value);
                }
            },'json');
        }
        $("#GDSType").change(gdsValidator);
        if(devObject.postData && devObject.postData['GDSType']){
            $("#GDSType").val(devObject.postData['GDSType']);
        }
        if($("#GDSType").val()){
            gdsValidator();
        }
        $(function(){
            $("#requestAjax").submit(function(e){
                devObject.submitRequestAjax();
            });
            //devObject.getProto();
            //devObject.clickProto();
            devObject.init();
            var menuYloc = $("#resultDiv").offset().top;
            $(window).scroll(function (){ 
                var offsetTop = menuYloc + $(window).scrollTop() +"px";
                $("#resultDiv").animate({top : offsetTop },{ duration:10 , queue:false });
            });
        });
    </script>
</html>
