/**
 *	Copyright 2009, 2010 Litwicki Media LLC
 *	@author:	jake@litwickimedia.com
 *	@SVN:		$Id$
 */

$().ready(function(){
	
	$("#profileform").validate({
		errorLabelContainer: "#profile-errors",
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
		success: userSuccess
	});
	
	$("#group_id").multiSelect({
		fadeSpeed: 500,
		selectedList: 2,
		minWidth: 300,
		noneSelectedText: "Select From Below",
		onCheck: function(){
			var numChecked =  $("[name='user_groups[]']:checked").length;

			if(numChecked == 0){
				$("#user-permissions").hide(2000);
				$("#user-permissions input").removeAttr("checked");
				$("#user-permissions input").change();
			} else {
				$("#user-permissions").show(1500);
			}
		}
	});
	
	$("#userlist").multiSelect({
		multiple: false,
		showHeader: false,
		noneSelectedText: "View Another Profile",
		selectedText: function(numChecked, numTotal, checkedItem){
			return $(checkedItem).attr("title");
		},
		onCheck: function(){
			window.location = '/users.php?id=' + this.value;
		}
	});
	
});

function userSuccess(data){
	if( data.user_error == '' ){

		var success_link = 'Saved profile for <a href="/users.php?id=' + data.user_id + '">' + data.user_realname + '</a> successfully!';
	
		$("#profileform").hide();
		$("#user-error").hide();
		$("#profile-saved").fadeIn(2500);
		$("#success-message").html(success_link);
		$("#user_phone").val(data.user_phone);

		return true;
	} else {
			
		$("#user-error").show("slow");
		$("#bad-email").text(data.bad_email);
		$("#user_name").text(data.user_name);
		$("#user_email").val('');
		return false;
		
	}
}