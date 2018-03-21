/* global deps, types, values */
function reset(prefix, name){
    if(types[name] === 'select'){
	var str = "<select name='"+name+"'>";
	var vals = values[name];
	for(var i in vals){
	    str += "<option value='"+i+"'>"+i+"-"+vals[i]+"</option>";
	}
	str += "</select>";
	$("#addForm [name='"+name+"'").parent().html(str);
    }else{
	$("#addForm [name='"+name+"'").parent().html('<input type="text" size="10" name="'+name+'">');
    }
    if(!deps[name]){
	return;
    }
    var depArr = deps[name];
    for(var j in depArr){
	var depKey = depArr[j];
	var vals = values[prefix + "_" + depKey];
	if(!vals){
	    $("#addForm [name='"+depKey+"'").parent().html('<input type="text" size="10" name="'+depKey+'">');
	}
    }
}
function handleDeps(item,prefix){
    var name = $(item).attr('name');
    if(!deps[name]){
	return;
    }
    if(undefined === prefix){
	prefix = name;
    }else{
	prefix = prefix + "_" + name;
    }
    var depArr = deps[name];
    $(item).change(function(){
	for(var j in depArr){
	    var depKey = depArr[j];
	    var vals = values[prefix + $(item).val() + "_" + depKey];
	    if(!vals){
		reset(prefix + $(item).val(), depKey);
		continue;
	    }
	    var str = "<select name='"+depKey+"'>";
	    for(var i in vals){
		str += "<option value='"+i+"'>"+i+"-"+vals[i]+"</option>";
	    }
	    str += "</select>";
	    $("#addForm [name='"+depKey+"'").parent().html(str);
	    $("#addForm select[name='"+depKey+"'").each(function(index2, item2){
		handleDeps(item2, prefix + $(item).val());
		$(item2).change();
	    });
	}
    });
}
$(document).ready(function(){
    $("#addForm select").each(function(index, item){
	handleDeps(item);
	$(item).change();
    });
});