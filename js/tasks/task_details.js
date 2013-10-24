/**
 *	Copyright 2009, 2010 Litwicki Media LLC
 *	@author:	jake@litwickimedia.com
 *	@SVN:		$Id$
 */

$().ready(function() {

	$("#timeform button#submitbutton").click(function(){
		if( $("#timeform").valid() ) {
			$("#timeform").validate().form();
			$("#timelog-form").dialog('close');
		} else {
			return false;
		}
	});

	$("#timeform").validate({
		rules: {
			work_date: "required",
			hours_worked: {
				required: true,
				min: 0.25,
				max: 8.00
			}
		}
	});
	
	$("#timeform").ajaxForm({
		dataType: 'json',
		clearForm: true,
		resetForm: true,
		success: addtime
	});

});

function addtime(data) { 

	$("#timelog").show("fast");
	$('#delete-modal').dialog('close');
	
	//fade in the new time row
	$(data.timelogrow).appendTo('#timelog-body');

	//update the task hours total
	$("#task_hours").text(data.task_hours);
	return true;
}

function deletetime(data) {
	var timerow = "#timerow" + data.task_log_id;
	//update the task hours total
	$("#task_hours").text(data.task_hours);
	
	if( data.task_hours == 0 ){
		$("#timelog").hide();
	}else{
		$(timerow).fadeOut("slow");
	}
	return true;
}