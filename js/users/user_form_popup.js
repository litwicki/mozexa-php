/**
 *	Copyright 2009, 2010 Litwicki Media LLC
 *	@author:	jake@litwickimedia.com
 *	@SVN:		$Id$
 */

$().ready(function() {
	
	{* force validation on userform *}
	$("button#btn_saveuser").click(function(){
		if( $("#adduser").valid() ) {
			$("#adduser").validate().form();
		} else {
			return false;
		}
	});
	
	$("#adduser").ajaxForm({
		clearForm: true,
		resetForm: true,
		dataType: 'json',
		success: {if $S_PROJECT}addProjectUser{else}addUser{/if}
	});
		
	$("#adduser").validate();
});
