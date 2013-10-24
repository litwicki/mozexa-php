/**
 *	Copyright 2009, 2010 Litwicki Media LLC
 *	@author:	jake@litwickimedia.com
 *	@SVN:		$Id$
 */
 
 $().ready(function() {
	$("#loginform").validate({
		errorElement: "p",
			errorPlacement: function(error, element) {
				error.appendTo('#errors');
			},
		rules: {
			username: "required",
			password: {
				required: true,
				minlength: 6
			}
		}
	});
});