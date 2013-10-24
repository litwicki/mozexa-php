/**
 *	Copyright 2009, 2010 Litwicki Media LLC
 *	@author:	jake@litwickimedia.com
 *	@SVN:		$Id$
 */

$().ready(function(){
	
	$("#paymentform").validate({
		errorLabelContainer: "#payment-errors"
	});
	
	$("button#paynow").click(function(){
		if( $("#paymentform").valid() ) {
			$("#paymentform").validate().form();
		} else {
			return false;
		}
	});
	
	$("#paymentform").ajaxForm({
		dataType: 'json',
		success: function(data){
			$("#payment-result").html(data.response);
			$("#payment-result").show('blind', 1500);
			return false;
		}
	});

	$(".confirmation").click(function(){
		if( $('.confirmation:checked').length == 2 ){
			$("#paynow").show();
		} else {
			$("#paynow").hide();
		}
	});
	
	$("#expire_month, #expire_year").multiSelect({
		multiple: false,
		showHeader: false,
		noneSelectedText: "Month",
		selectedText: function(numChecked, numTotal, checkedItem){
			return $(checkedItem).attr("title");
		},
		minWidth: 100
	});
	
});
