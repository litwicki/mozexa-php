/**
 *	Copyright 2009, 2010 Litwicki Media LLC
 *	@author:	jake@litwickimedia.com
 *	@SVN:		$Id$
 */

$().ready(function() {
	
	$("#invoiceform").validate({
		errorLabelContainer: "#errors"
	});
	
	$("#invoiceform").ajaxForm({
		dataType: 'json',
		success: function(data){
			$("#create-invoice").fadeOut();
			$("#success").fadeIn();
			
			var message = '<a href="/invoices.php?id=' + data.invoice_id + '">Invoice #' + data.invoice_id + '</a> saved successfully! '+
			'<a href="/invoices.php?mode=recurrence&client_id=' + data.client_id + '">Click Here</a> to setup invoice profiles for this invoice.';
			
			$("#invoice-success").html(message);
			return false;
		}
	});
	
	$(function() {
		$(".invoice-date").datepicker({
			minDate: "+1"
		});
	});
	
	$("#invoice_date_due").change(function(event){
		var invoice_date_due = new Date($(this).val());
		var invoice_date = new Date($("#invoice_date").val());

		if( invoice_date_due <= invoice_date ){
			alert('Due Date must be AFTER Invoice Date!');
			$(this).val('');
		}
	});
	
	$("#add-item").click(function(){
		var itemCount = $('#invoice-items >tbody >tr').length;
		var item = '<tr class="ui-widget-content" id="invoice-item-' + (itemCount+1) + '">'+
					'<td><input type="text" class="ui-input" name="item_name[]" id="item_name' + (itemCount+1) + '" validate="required:true" style="width: 200px;" title="Item name is required!" /></th>'+
					'<td><input type="text" class="ui-input" name="item_description[]" id="item_description' + (itemCount+1) + '" style="width: 400px;" validate="required:true" title="Item description is required!" /></td>'+
					'<td><input type="text" class="ui-input" name="item_price[]" onblur="item_price(this.value)" id="item_price' + (itemCount+1) + '" validate="number:true,required:true" title="Item price is required!" /></td>'+
					'<td><a href="javascript:;" class="noborder" onclick="remove_row(\'invoice-item-' + (itemCount+1) + '\');"><span class="delete ico">&nbsp;</span></a>'+
				'</tr>';
		$("#invoice-items tbody").append(item);
		return true;
	});
	
	$("#add-discount").click(function(){
		var itemCount = $('#invoice-discounts >tbody >tr').length;
		var item = '<tr class="ui-widget-content" id="invoice-discount-' + (itemCount+1) + '">'+
					'<td><input type="text" class="ui-input" name="discount_name[]" id="discount_name' + (itemCount+1) + '" validate="required:true" style="width: 200px;" title="Discount name is required!" /></th>'+
					'<td><input type="text" class="ui-input" name="discount_reason[]" id="discount_reason' + (itemCount+1) + '" style="width: 400px;" validate="required:true" title="Discount reason is required!" /></td>'+
					'<td><input type="text" class="ui-input" name="discount_amount[]" onblur="item_price(this.value)" id="discount_amount' + (itemCount+1) + '" validate="number:true,required:true" title="Discount amount is required!" /></td>'+
					'<td><a href="javascript:;" class="noborder" onclick="remove_row(\'invoice-discount-' + (itemCount+1) + '\');"><span class="delete ico">&nbsp;</span></a>'+
				'</tr>';
		$("#invoice-discounts tbody").append(item);
		return true;
	});
	
});

function remove_row(itemId){
	$("#" + itemId).hide();
	$("#" + itemId).remove();
	return true;
}


function item_price(price){

	if(price != ''){
		if(item_price <= 0){
			alert(price + ' - Item price must be a valid number greater than 0. Do not include the "$"');
			return false;
		}
	}
	
	return true;
}