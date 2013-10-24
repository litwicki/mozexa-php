/**
 *	Copyright 2009, 2010 Litwicki Media LLC
 *	@author:	jake@litwickimedia.com
 *	@SVN:		$Id$
 */

$().ready(function() {
	
	$("form#replyform button#submitbutton").click(function(){
		if( $("#replyform").valid() ) {
			$("#replyform").validate().form();
		} else {
			return false;
		}
	});

	$(".replybox").click(function(){
		$("#replybox").slideToggle("normal");
		$(this).toggleClass("active"); 
		return false;
	});
	
	$("#replyform").validate();
	
	$('#replyform').ajaxForm({ 
        dataType: 'json',
		clearForm: true,
		resetForm: true,
        beforeSerialize: function(){
			tinyMCE.triggerSave();
		},
		success: processJson 
    }); 

});

function processJson(data) { 
	$(data.replyrow).hide().prependTo("#commentsbox").fadeIn("slow");
	$("#reply_count_message").text(data.reply_count_message);
	$("#replybox").fadeOut("slow");
	return true;
}


function checkTable(id){
	$('#' + id).remove();
	var rowCount = $('#mymessages >tbody >tr').length;
	if(rowCount == 0){
		location.reload();
	}
	return true;
}