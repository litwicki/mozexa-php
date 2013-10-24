/**
 *	Copyright 2009, 2010 Litwicki Media LLC
 *	@author:	jake@litwickimedia.com
 *	@SVN:		$Id$
 */

$().ready(function() {
	
	$(".taskbox").click(function(){
		$("#taskbox").fadeIn("slow");
		$("#complete").fadeOut("slow");
		return false;
	});

	$("#taskform button#save").click(function(){
		if( $("#taskform").valid() ) {
			$("#taskform").validate().form();
		} else {
			return false;
		}
	});
	
	$("#taskform").validate({
		errorLabelContainer: "#errors"
	});
	
	$('#taskform').ajaxForm({
		clearForm: true,
		resetForm: true,
		dataType: 'json',
		beforeSerialize: function(){
			tinyMCE.triggerSave();
		},
		success: saveTask
	});
	
});

function saveTask(data) { 
	
	//$("#taskbox").fadeOut("slow");
	
	var task_html = '<a href="/tasks.php?id=' + data.task_id + '">' + data.task_name + '</a> saved successfully for <a href="/projects.php?id=' + data.project_id + '">' + data.project_name + '</a>';
	$("#task-success").html(task_html);
	$("#complete").fadeIn("normal");
	
	setTimeout( function(){
		window.location = '/tasks.php?id=' + data.task_id;
	}, 5000);
	
	return true;
}
