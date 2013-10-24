/**
 *	Copyright 2009, 2010 Litwicki Media LLC
 *	@author:	jake@litwickimedia.com
 *	@SVN:		$Id$
 */

$().ready(function(){

	$("button#save").click(function(){
		if( $("#messageform").valid() ) {
			$("#messageform").validate().form();
		} else {
			return false;
		}
	});

	$("#messageform").ajaxForm({
		dataType: 'json',
		beforeSerialize: function(){
			tinyMCE.triggerSave();
		},
        success: saveMessage
	});
	
	$("#messageform").validate({
		errorLabelContainer: $("#errors")
	});
	
	
	$("#message_type_1").click(function(){
		$("#projectlist").show();
		$("#messageuser").hide();
		$("#messageuser").val();
	});
	
	$("#message_type_2").click(function(){
		$("#projectlist").hide();
		$("#messageuser").show();
		$("#project_id").val(0);
	});
	
	$("#message_user_id").autocomplete({
		source: '/autocomplete.php?type=messageuser',
		dataType: 'json',
		minlength: 0,
		select: function(event, data) {
			$("#user_id").val(data.item.value);
			$("#message_user_id").val(data.item.label);
			return false;
		},
		focus: function(event, data) {
			$('#message_user_id').val(data.item.label);
			return false;
		}
	});
	
});

function saveMessage(data){
	var message = data.item_type + ': <a href="' + data.item_link + '">' + data.item_subject + '</a> saved successfully! You will be redirected in 5 seconds.';
	$("#success").show("slow");
	$("#success_message").html(message);
	
	setTimeout( function(){
		window.location = data.item_link;
	}, 5000);
}
