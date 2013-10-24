/**
 *	Copyright 2009, 2010 Litwicki Media LLC
 *	@author:	jake@litwickimedia.com
 *	@SVN:		$Id$
 */
 
$(document).ready(function() { 

	$(function() {
		$("#settings").tabs({
			cookie: {
				expires: 1
			}
		});
	});
	
	$("#ratelist").ajaxForm({
		dataType: 'json',
		success: function(data) {
			$("#rate_name").val(data.name);
			$("#rate_description").val(data.description);
			$("#rate_cost").val(data.cost);
			$("#rate_id").val(data.rate_id);
			$("#delete_rate_id").val(data.rate_id);
			
			$("#deleterateform").show();
			
			if(data.hourly == 1){
				$("#hourly").attr("checked", "checked");
				$("#hourly").change();
			} else {
				$("#hourly").removeAttr("checked");
				$("#hourly").change();
			}
			return false;
		}
	});
	
	$("#ratelist .ui-custom-input").click(function(){
		$("#ratelist").submit();
	});
	
	$("#deleterateform").ajaxForm({
		dataType: 'json',
		clearForm: true,
		resetForm: true,
		success: function(data){
			$("#raterow-" + data.rate_id).hide();
			$("#rate_name").val("");
			$("#rate_description").val("");
			$("#rate_cost").val("");
			$("#rate_id").val("");
			$("#delete_rate_id").val("");
			
			$("#deleterateform").hide();
			
			if(data.hourly == 1){
				$("#hourly").attr("checked", "checked");
				$("#hourly").change();
			} else {
				$("#hourly").removeAttr("checked");
				$("#hourly").change();
			}
			return false;
		}
	});
	
	$("#rateform").ajaxForm({
		dataType: 'json',
		clearForm: true,
		resetForm: true,
		success: function(data){
		
			var rateType = '';
			if( data.flat_rate == 0 ){
				rateType = 'Hourly';
			} else {
				rateType = 'Service';
			}

			if(data.new_rate){
				var newRate = '<tr class="ui-state-active" id="raterow-' + rate_id + '">'+
								'<td></td>'+
								'<td>'+data.name+'</td>'+
								'<td>'+data.description+'</td>'+
								'<td>'+rateType+'</td>'+
								'<td>$'+data.cost+'</td>'+
							'</tr>';
							
				$("#ratestable").append(newRate);
			} else {
				var rate_id = data.rate_id;
				$("#rate-" + rate_id + "-name").text(data.name);
				$("#rate-" + rate_id + "-description").text(data.description);
				$("#rate-" + rate_id + "-cost").text(data.cost);
				$("#rate-" + rate_id + "-type").text(rateType);

				$("#raterow-" + rate_id).effect("highlight", {}, 2000, callback(rate_id));
			}
			
			$("#flat_rate_yes").change();
			$("#flat_rate_no").change();
			$("#rate-" + rate_id).change();
			$("#deleterateform").hide();
			
		}
	});
	
	$("#rateform").validate({
		errorLabelContainer: "#rateerrors"
	});
	
	$("#saverate").click(function(){
		if( $("#rateform").valid() ) {
			$("#rateform").validate().form();
		} else {
			return false;
		}
	});
	
	$("#resetrate").click(function(){
		var rate_id = $("#rate_id").val();
		$("#rate-" + rate_id).removeAttr('checked');
		$("#rate-" + rate_id).change();
		$("#rate_id").val('');
		$("#deleterateform").hide();
		return true;
	});

});

function callback(rate_id){
	setTimeout(function(){
		$("#raterow-" + rate_id + ":hidden").removeAttr('style').hide().fadeIn();
	}, 4000);
	return false;
}