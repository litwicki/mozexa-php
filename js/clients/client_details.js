/**
 *	Copyright 2009, 2010 Litwicki Media LLC
 *	@author:	jake@litwickimedia.com
 *	@SVN:		$Id$
 */

$().ready(function() {

	$("#client_user").autocomplete({
		source: '/autocomplete.php?type=clientuser',
		dataType: 'json',
		minLength: 3,
		select: function(event, data) {
			$("#add_user_id").val(data.item.value);
			$("#client_user").val(data.item.label);
			
			toggleAddUserButton();
			
			return false;
		},
		focus: function(event, data) {
			$('#client_user').val(data.item.label);
			return false;
		}
	});
	
	$("#client_user").blur(function(){
		toggleAddUserButton();
	});
	
	$(".delete-clientuser").click(function(){
		var user_id = $("#client_user_id").val();
		$("#delete-" . user_id).submit();
	});

});

function toggleAddUserButton(){

	if( $("#client_user").val() == '' ){
		$("#add_user_id").val('');
		$("#adduser").hide();
	} else {
		$("#adduser").show();
	}
}