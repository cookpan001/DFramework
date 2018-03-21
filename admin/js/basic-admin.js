/* global types, values, deps, bitops */
var rely = {};
for(var i in deps){
    for(var j in deps[i]){
	rely[deps[i][j]] = i;
    }
}
function getRelyValue(item, data_key)
{
    var key = data_key;
    var cur = data_key;
    var obj = $(item).parent();
    while(rely[cur]){
	var val = obj.find("[key='"+rely[cur]+"']").html();
	key = rely[cur]+val+"_"+key;
	cur = rely[cur];
    }
    return values[key];
}
$(document).ready(function(){
    if($(".time").length){
	$(".time").datetimepicker({
	    //showSecond: true,
	    //showTimezone: true,
	    //timezone: '+0000',
	    //timezoneList: ['+0000'],
	    dateFormat: "yy-mm-dd",
	    timeFormat: 'HH:mm:ss',
	    controlType: 'select'
	});
    }
    $("tr[class='data_line'] td").each(function(index, item){
        var data_key = $(item).attr('key');
        $(item).dblclick(function(){
            if($(item).attr('edit') == 0){
                return;
            }
            var data = $(item).html();
            if(0 === data.indexOf("<input") || 0 === data.indexOf("<select") || 0 === data.indexOf("<textarea")){
                return;
            }
	    var vals = getRelyValue(item, data_key);
	    if(rely[data_key] && vals){
		var str = "<select>";
		str += "<option value='"+data+"'>"+data+"-"+vals[data]+"</option>";
		for(var i in vals){
		    if(i === data){
			continue;
		    }
		    str+="<option value='"+i+"'>"+i+"-"+vals[i]+"</option>";
		}
		$(item).html(str+"</select>");
	    }else if(types[data_key] && types[data_key] === 'select'){
		var str = "<select>";
		str += "<option value='"+data+"'>"+data+"-"+values[data_key][data]+"</option>";
		for(var i in values[data_key]){
		    if(i === data){
			continue;
		    }
		    str+="<option value='"+i+"'>"+i+"-"+values[data_key][i]+"</option>";
		}
		$(item).html(str+"</select>");
	    }else if(types[data_key] && types[data_key] === 'checkbox'){
		var str = "";
		var line = 0;
		var arr = data.split(',', bitops[data_key]);
		for(var i in values[data_key]){
		    if(bitops.indexOf(data_key) > -1 && ((1 << i) & data) > 0){
			str+="<input name='"+data_key+"' type='checkbox' value='"+i+"' checked />"+i+"-"+values[data_key][i];
		    }else if(bitops.indexOf(data_key) < 0 && arr.indexOf(i) > -1){
			str+="<input name='"+data_key+"' type='checkbox' value='"+i+"' checked />"+values[data_key][i];
		    }else{
			str+="<input name='"+data_key+"' type='checkbox' value='"+i+"' />"+values[data_key][i];
		    }
		    if(line > 0 && line % 4 === 0){
			str+="<br/>";
		    }
		    line++;
		}
		$(item).html(str);
	    }else if(types[data_key] && types[data_key] === 'textarea'){
		$(item).html("<textarea>"+data+"</textarea>");
	    }
	    else{
		$(item).html("<input type='text' value='"+data+"' />");
	    }
            $(item).find("input,textarea,select,checkbox").keyup(function(event){
                var val = $(event.target).val();
		var type = event.target.type;
		if(type === 'checkbox'){
		    if(bitops.indexOf(data_key) > -1){
			val = 0;
			$("input[type='checkbox'][name='"+data_key+"']:checked").each(function(index, item){
			    val += 1 << $(item).val();
			});
		    }else{
			val = [];
			$("input[type='checkbox'][name='"+data_key+"']:checked").each(function(index, item){
			    val.push($(item).val());
			});
			val = val.join(',');
		    }
		}
                if(event.ctrlKey && event.keyCode === 13){
                    var where = $(item).parent().attr('key');
		    val = $.trim(val);
                    $.post('?', {"ajax":1, "action": "update","where":where, "key": data_key, "val": val}, function(ret){
                        if(ret){
                            $(item).html(val);
                        }
                    });
                }else if(event.keyCode === 27){//esc
                    $(item).html(val);
                }
            });
        });
    });
});