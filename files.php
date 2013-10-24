<?php

/****
 *
 *  @author: jake@litwickimedia.com
 *  @SVN: $Id: projects.php 43 2010-06-06 03:32:59Z jake $
 *  @Copyright 2009,2010 Litwicki Media LLC
 *
 ***/

define('MY_DASHBOARD', true);
$root_path = './';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($root_path . 'common.' . $phpEx);

//setup user
$user->setup();
$user_id = (int) $user->data['user_id'];
$username = $user->data['user_realname'];

if( !$user_id )
{
	login_box("$base_url");
}

//setup user permissions
$auth->setup($user_id);

//build user dashboard
$dashboard = new dashboard();
$dashboard->setup($user_id);

//adding a new project
if( isset($_POST['save']) )
{
	

}
elseif( isset($_POST['delete']) )
{
	$file_id = (int) $_POST['file_id'];
	$dashboard->change_status('file', $file_id, $delete_status);
	redirect($base_url);
}
else
{
	/*
	* Are we trying to add, or view a file?
	*/
	if( isset($_GET['mode']) )
	{
		$mode = $_GET['mode'];
	}

	if( isset($_GET['id']) )
	{
		$file_id = (int) $_GET['id'];
		
		$file_row = $file->detail($file_id);
		
		//is this a valid project?
		if(empty($file_row))
		{
			//invalid project_id, so display a full list with an error
			$file_count = $file->list_all($user_id);
			$form_error = "Invalid File Id Specified!";
			$show_form = false;
			$show_detail = false;
		}

		if( $mode == "edit" )
		{
			if( ($auth->options['U_EDIT_FILE'] && $auth->user_group['S_STAFF']) || $auth->user_group['S_ADMINISTRATOR'] )
			{
				$page_title = "Edit File: $project_name";
			}
			else
			{
				$form_error = 'You are not authorized to edit files!';
				$file_count = $file->list_all($user_id);
				$page_title = "My Files ($file_count)";
			}
		}
		else
		{
			$page_title = $file_name;
		}
		
		$show_form = ($mode == "edit" ? true : false);
	}
	else
	{
		if( $mode == "add" )
		{
			if( ($auth->options['U_ADD_FILE'] && $auth->user_group['S_STAFF']) || $auth->user_group['S_ADMINISTRATOR'] )
			{
				$page_title = "Add New File";
			}
			else
			{
				$file_count = $file->list_all($user_id);
				$page_title = "My Files ($file_count)";
				$form_error = 'You are not authorized to add a new file!';
			}
		}
		else
		{
			$file_count = $file->list_all($user_id);
			$page_title = "My Files ($file_count)";
		}
		
		$show_form = $mode == "add" ? true : false;
		
	}
}

//global defaults
$template->assign(array(
	'S_FILE'				=>	true,
	'SUCCESS_LINK'			=>	$success_link,
	'FILE_ERROR'			=>	$form_error,
	'SHOW_DETAIL'			=>	$show_detail,
	'SHOW_FORM'				=>	$show_form,
	'FORM_NAME'				=>	$page_title,
	'NO_FILES'				=>	"No files found!",
	'S_DASHBOARD_PAGE'		=>	true,
));

//spit out the page
page_header($page_title);

$template->display('template/file_body.html');

page_footer();

?>
