/**
 *	Copyright 2009, 2010 Litwicki Media LLC
 *	@author:	jake@litwickimedia.com
 *	@SVN:		$Id$
 */

$().ready(function() {
	$("#request_form").validate();
	
	$(".new_company").click(function(){
		$("#clientlist").val(0);
		$("#new_company").slideToggle("slow");
		$(this).toggleClass("active"); 
		return false;
	});
	
	$("#clientlist").change(function () {
		if( $("#clientlist").val() == 999 ){
			$("#new_company").slideToggle("normal");
			$("#new_company").toggleClass("active");
			$("#company").addClass("required");
		} else {
			$("#company").removeClass("required");

			$("#new_company").hide("slow");
			$("#company-error").hide("fast");
		}
	})
	.change();
	
	{* Make sure we validate company textbox if there is no select list for companies *}
	if( $('#clientlist option').size() == 0 ){
		$("#company").addClass("required");
	}
	
	$("button#submit").click(function(){
		if( $("#request_form").valid() ) {
			$("#request_form").validate().form();
		} else {
			return false;
		}
	});
	
	$('#request_form').ajaxForm({
		resetForm: true,
		beforeSerialize: function(){
			tinyMCE.triggerSave();
		},
        success: sendRequest
	});
	
});

function sendRequest(){
	$("#formdata").hide("fast");
	$("#request-success").show("slow");
	$("#formbox").removeClass("bg3");
	$("#formbox").addClass("bg2");
	return true;
}