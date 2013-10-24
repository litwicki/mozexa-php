/**
 *	Copyright 2009, 2010 Litwicki Media LLC
 *	@author:	jake@litwickimedia.com
 *	@SVN:		$Id$
 */
 
$().ready(function() {

	$("#select_client_id").autocomplete({
		source: '/autocomplete.php?type=invoice',
		dataType: 'json',
		minlength: 0,
		select: function(event, data) {
			$("#client_id").val(data.item.value);
			$("#select_client_id").val(data.item.label);
			
			if( data.item.value > 0 ){
				$("#invoicesearch").submit();
			}

			return false;
		},
		focus: function(event, data) {
			$('#select_client_id').val(data.item.label);
			return false;
		}
	});
	
	$("#select_client_id").focus(function() {
		if( this.value == this.defaultValue ) {
			this.value = "";
		}
	}).blur(function() {
		if( !this.value.length ) {
			this.value = this.defaultValue;
		}
	});
	
	$("#recurrenceform .ui-custom-input").click(function(){
		var rate_id = $(this).val();
		if( $(this).is(':checked') ){
			$("#raterow-" + rate_id + " input:text").removeAttr('disabled');
			$("#raterow-" + rate_id + " input:text").switchClass('ui-state-disabled', 'ui-input', false);
		} else {
			$("#raterow-" + rate_id + " input:text").attr('disabled', 'disabled');
			$("#raterow-" + rate_id + " input:text").val('');
			$("#raterow-" + rate_id + " input:text").switchClass('ui-input', 'ui-state-disabled', false);
		}
		
		toggleSaveButton();
		
	});
	
	$("#recurrenceform").validate({
		errorLabelContainer: "#rate-errors"
	});
	
	$("#recurrenceform").ajaxForm({
		dataType: 'json',
		clearForm: true,
		resetForm: true,
		success: function(data){
			$("#details").hide();
			$("#response").delay(1000).show();
			$("#response-message").html(data.response);
			return false;
		}
	});
	
	$("button#saverecurrences").click(function(){
		if( $("#recurrenceform").valid() ) {
			$("#recurrenceform").validate().form();
		} else {
			return false;
		}
	});
	
	$(".end-date").change(function(){
	
		var rate_id = $(this).attr('id').replace(/end-date-/, '');
		var end_date = new Date($(this).val());
		var start_date = new Date($("#start-date-" + rate_id).val());

		if( end_date <= start_date ){
			alert('End date must be AFTER start date!');
			$(this).val('');
		}
		
	});
	
	toggleSaveButton();
	
});

function toggleSaveButton(){
	var rates_checked = parseInt($("#recurrenceform :checkbox:checked").length);

	if( rates_checked > 0 ){
		$("#saverecurrences").show();
	} else {
		$("#saverecurrences").hide();
	}
}