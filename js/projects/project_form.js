/**
 *	Copyright 2009, 2010 Litwicki Media LLC
 *	@author:	jake@litwickimedia.com
 *	@SVN:		$Id$
 */

$().ready(function() {

	$(".projectbox").click(function(){
		$("#projectbox").fadeIn("slow");
		$("#complete").fadeOut("slow");
		return false;
	});

	$("button#save").click(function(){
		if( $("#projectform").valid() ) {
			$("#projectform").validate().form();
		} else {
			return false;
		}
	});

	$("#projectform").ajaxForm({
		dataType: 'json',
		beforeSerialize: function(){
			tinyMCE.triggerSave();
		},
        success: saveProject
	});
	
	$("#projectform").validate({
		errorLabelContainer: $("#project-error")
	});

});

//non jQuery functions
function saveProject(data) { 
	//$("#projectbox").fadeOut("slow");
	$("#project-success").html('Project: <a href="/projects.php?id=' + data.project_id + '">' + data.project_name + '</a> saved successfully! You will be redirected in 5 seconds.');
	$("#complete").fadeIn("normal");
	
	setTimeout( function(){
		window.location = '/projects.php?id=' + data.project_id;
	}, 5000);
	
	return true;
}