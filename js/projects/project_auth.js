/**
 *	Copyright 2009, 2010 Litwicki Media LLC
 *	@author:	jake@litwickimedia.com
 *	@SVN:		$Id$
 */

$().ready(function() {

	$("#authform").ajaxForm({
		dataType: 'json',
		success: function(){
			$("#authsuccess").fadeIn(2500).delay(2000).fadeOut(1000);
		}
	});

});