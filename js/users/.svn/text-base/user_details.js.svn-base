/**
 *	Copyright 2009, 2010 Litwicki Media LLC
 *	@author:	jake@litwickimedia.com
 *	@SVN:		$Id$
 */

$().ready(function() {
	
	$("#profileform").validate({
		rules: {
			new_password_confirm: {
				minlength: 6,
				equalTo: "#new_password"
			}
		}
	});
	
	$("button#saveprofile").click(function(){
		if( $("#profileform").valid() ) {
			$("#profileform").validate().form();
		} else {
			return false;
		}
	});
	
	$("#profileform").ajaxForm({
		dataType: 'json',
		success: saveProfile
	});

});

function saveProfile(data){
	if( data.user_error == true )
	{
		$("#user-error").show("slow");
		$("#bad-email").text(data.user_email);
		$("#user_name").text(data.user_name);
		$("#user_email").text(data.user_email);
		return false;
	}
	else
	{
		$("#profile_user_id").val(data.user_id);
		$("#user_firstname").val(data.user_realname);
		$("#user_lastname").val(data.user_lastname);
		$("#user_aim").val(data.user_aim);
		$("#user_email").val(data.user_email);
		$("#user_phone").val(data.user_phone);
		
		if( data.user_sms == 1 ){
			$("#user_sms").attr("checked","checked");
		} else {
			$("#user_sms").removeAttr("checked");
		}
		
		$("#profile-saved").show("slow");
		$("#profile-saved").fadeIn(1000).delay(2000).fadeOut(500);
		return true;
	}
}
