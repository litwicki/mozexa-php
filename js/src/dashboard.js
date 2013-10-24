/**
 *	Copyright 2009, 2010 Litwicki Media LLC
 *	@author:	jake@litwickimedia.com
 *	@SVN:		$Id$
 */
 
$().ready(function(){

	//allow validation to pickup inline validation calls
	$.metadata.setType("attr", "validate");

	/**
	 *	Submit HTML Editor data before the editor is loaded
	 *	and inadvertently blanks out the .text of the editor init();
	 *	Source: http://maestric.com/doc/javascript/tinymce_jquery_ajax_form
	 */
	
	var editorCount = $('.wysiwyg').length + $('.wysiwyg_simple').length + $('.wysiwyg_plain');
	
	if(editorCount > 0){
		$('form').bind('form-pre-serialize', function(e) {
			tinyMCE.triggerSave();
		});
	}

	var WYSIWYG_OPTIONS = {
		script_url : '/js/tiny_mce/tiny_mce.js',
		theme : "advanced",
		entity_encoding : "raw",
		plugins : "paste,fullscreen,xhtmlxtras",

		theme_advanced_buttons1: "fullscreen,|,undo,redo,|,link,unlink,|,bold,italic,underline,|,paste,pasteword,|,bullist,numlist,|,justifyleft,justifycenter,justifyright,justifyfull,|,code",
		theme_advanced_buttons2: "",
		theme_advanced_buttons3: "",
		theme_advanced_toolbar_location : "top",
		theme_advanced_toolbar_align : "left",
		theme_advanced_statusbar_location : "bottom",
		theme_advanced_resize_horizontal : false,
		theme_advanced_resizing : true
	};

	$(".wysiwyg_simple, .wysiwyg").tinymce(WYSIWYG_OPTIONS);
	
	//all input.date-pick should be date selectors
	$(function() {
		$(".date-pick").datepicker({
			minDate: 0,
			numberOfMonths: 2,
			showOn: 'button', 
			buttonImage: '/images/icons/fugue/calendar-blue.png', 
			buttonImageOnly: false
		});
	});
	
	$(function() {
		$(".inline-date-pick").datepicker({
			minDate: 0,
			numberOfMonths: 2
		});
	});
	
	//tooltip Settings
	$('img, a, .ico').tooltip({ 
		track: true, 
		showURL: false, 
		showBody: " - ", 
		fade: 1000
	});
	
	$('label.error').tooltip({
		track: false,
		showURL: false,
		fade: 500
	});
	
	/**
	 *	Get the modal popup window for any .prompt
	 */
	$(".prompt").click(function(){
		var id = $(this).attr('rel');
		$(id).dialog('open');
	});

	$(function() {
		$(".modal, .dialog").dialog({
			autoOpen: false,
			show: 'blind',
			hide: 'explode',
			modal: true,
			overlay: { backgroundColor: '#000', opacity: 0.5 },
			minWidth: 300
		});
	});
	
	$(".dialog-wysiwyg").dialog({
		bgiframe:true,
		autoOpen: false,
		//show: 'blind',
		hide: 'explode',
		modal: true,
		resizable: false,
		width: 650,
		open: function(event, id){
			$(".wysiwyg_plain").tinymce({
				script_url : '/js/tiny_mce/tiny_mce.js',
				theme : "advanced",
				entity_encoding : "raw",

				plugins : "paste,xhtmlxtras",

				theme_advanced_buttons1: "code,|,link,unlink,|,bold,italic,underline,|bullist,numlist,|,justifyleft,justifycenter,justifyright,justifyfull",
				theme_advanced_buttons2: "",
				theme_advanced_buttons3: "",
				theme_advanced_toolbar_location : "top",
				theme_advanced_toolbar_align : "left",
				theme_advanced_statusbar_location : "bottom",
				theme_advanced_resize_horizontal : false,
				theme_advanced_resizing : false
			});
		},
		beforeClose:function(event, id) {
			tinyMCE.triggerSave();
			var i, t = tinyMCE.editors;
			for (i in t){
				if (t.hasOwnProperty(i)){
					t[i].remove();
				}
			}
		}
	});

	$(".multiselect").multiSelect({ 
		position: "bottom",
		fadeSpeed: 500,
		selectedList: 5,
		minWidth: 250,
		noneSelected: "Select From Below"
	});
		
	$(".radiodropdown").multiSelect({
		multiple: false,
		showHeader: false,
		selectedText: function(numChecked, numTotal, checkedItem){
			return $(checkedItem).attr("title");
		}
	});

	//set default jqueryui speeds
	$.fx.speeds._default = 1000;
	
	//make popups draggable around the window
	$(function() {
		$(".ui-dialog").draggable({ handle: ".ui-dialog-titlebar" });
	});

	/* make buttons pretty */
	$(function() {
		$("button, tr.ui-widget-content input, .button").button();
	});
	
	//any radio inputs within .radiobuttons will 
	//be wrapped in jqueryui button elements
	$(function() {
		$(".radiobuttons, .checkboxlist").buttonset();
	});
	
	$('#dashboard-menu li').hover(function() {
	  $(this).addClass('ui-state-hover');
	}, function() {
		if($(this).attr('id') != 'ui-selected'){
			$(this).removeClass('ui-state-hover');
		}
	});
	
});

//very very basic toggles for form elements
function show(id){
	$("#" + id).show("slow");
	return true;
}

function hide(id){
	$("#" + id).hide("slow");
	return true;
}

function toggle(id){
	$('#' + id).toggle("slow");
    return false;
}

function clear_input(id){
	$("#" + id).val("");
	return true;
}