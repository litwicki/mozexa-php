/**
 *	Copyright 2009, 2010 Litwicki Media LLC
 *	@author:	jake@litwickimedia.com
 *	@SVN:		$Id$
 */

$().ready(function() {

	$("#clientform").validate({
		errorLabelContainer: "#errors"
	});
	
	$("button#saveclient").click(function(){
		if( $("#clientform").valid() ) {
			$("#clientform").validate().form();
		} else {
			return false;
		}
	});
	
	$("#clientform").ajaxForm({
		dataType: 'json',
		success: function(data){
			var company_link = '<a href="/clients.php?id='+data.client_id+'">'+data.company+'</a>';
			
			$("#company_name").html(company_link);
			//$("#client-form-wrapper").hide();
			$("#success").show("slow");
			
			setTimeout( function(){
				window.location = '/clients.php?id=' + data.client_id;
			}, 5000);
			
			return true;
		}
	});
	
});