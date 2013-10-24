/**
 *	Copyright 2009, 2010 Litwicki Media LLC
 *	@author:	jake@litwickimedia.com
 *	@SVN:		$Id$
 */

$().ready(function() {

	$("#completeform").validate({
		errorElement: "p",
			errorPlacement: function(error, element) {
				error.appendTo('#errors');
			},
		rules: {
			terms: {
				required: true,
				min: 1
			}
		},
		messages: {
			terms: "You must accept the completion terms to complete this project."
		}
	});
	
	$(function() {
		$("#project-details").tabs();
	});
		
});