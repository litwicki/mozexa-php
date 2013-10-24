<?php

/****
 *
 *  @author: jake@litwickimedia.com
 *  @SVN: $Id: projects.php 49 2010-06-08 14:01:42Z jake $
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
	$project_id 			= (int) $_POST['project_id'];
	$project_description 	= sanitize($_POST['project_description']);
	$project_name 			= sanitize($_POST['project_name']);
	$client_id				= (int) $_POST['project_client_id'];
	
	$project_row = array(
		'project_name'			=>	$project_name,
		'project_description'	=>	$project_description,
		'client_id'				=>	$client_id,
		'start_date'			=>	time(),
		'status_date'			=>	time(),
		'contract_required'		=>	isset($_POST['contract_required']) ? 1 : 0,
	);

	if($project_id === 0)
	{
		//new project, status is "open" by default
		$project_row['status'] = 1;
		
		//user_id of project creator
		$project_row['user_id'] = $user_id;
	
		$project_id = $dashboard->add_project($project_row);
	}
	else
	{
		$dashboard->modify_project($project_id, $project_row);
	}
	
	/**
	 *	Manage Project Managers & General Assigned Users
	 *	Project Managers are given admin permissions for this project_id
	 *	regardless of their global permissions.
	 */
	
	if( !empty($_POST['project_users']) )
	{
		$selected_users = $_POST['project_users'];
		$assigned_users = $dashboard->get_assigned_users('project', $project_id);
		$dashboard->manage_user_assignments('project', $project_id, $selected_users, $assigned_users);
	}
	
	if( !empty($_POST['project_managers']) )
	{
		$selected_users = $_POST['project_managers'];
		$assigned_users = $dashboard->get_project_managers($project_id, false);
		
		$dashboard->manage_pm($project_id, $selected_users, $assigned_users);
	}

	$json_array = array(
		'project_id'	=>	$project_id,
		'project_name'	=>	$project_name,
	);
	
	$json = json_encode($json_array);
	echo $json;
	exit;

}
elseif( isset($_POST['saveauth']) )
{
	$project_id = (int) $_POST['project_id'];
	$this_user_id = (int) $_POST['user_id'];
	
	foreach($_POST as $key => $value)
	{
		if( preg_match('/^u_.*/',$key) )
		{
			$project_auth[$key] = $value;
		}
	}
	
	foreach($project_auth as $auth_option => $auth_setting)
	{
		$auth_option_id = $auth->auth_option_id($auth_option);
		$row = $auth->get_permission($this_user_id, $auth_option_id, $project_id);

		if(empty($row))
		{
			$auth_row = array(
				'auth_option_id'	=>	$auth_option_id,
				'user_id'			=>	$this_user_id,
				'auth_setting'		=>	$auth_setting,
				'project_id'		=>	$project_id,
			);
			
			$auth->add_permission($auth_row);
		}
		else
		{
			$auth->edit_permission($this_user_id, $auth_option_id, $auth_setting, $project_id);
		}
	}

	exit;
	
}
//deleting a project (really just changing its status)
elseif( isset($_POST['delete']) )
{
	$project_id = $_POST['project_id'];
	$dashboard->change_status('project', $project_id, $delete_status);
	redirect($base_url);
}
//completing a project
elseif( isset($_POST['complete']) )
{
	$project_id 	= $_POST['project_id'];
	$project_row 	= $dashboard->get_project_detail($project_id);
	$project_name 	= $project_row['project_name'];
	$client_id 		= $project_row['client_id'];
	
	$dashboard->change_status('project', $project_id, $complete_status);
	
	$subject = "Project Approved: $project_name";
	$message = "$username approved the completion of project: $project_name\n\nProject Details: $base_url/projects.php?id=$project_id";
	
	//email staff that the project was finalized
	notify_staff($subject, $message, $priority = 5);
	
	//email client a reminder
	$dashboard->dashboard_email($subject, $message, $client_id);
	
	redirect($base_url);
}
else
{
	/*
	* Are we trying to add, edit, or view a proposal?
	*/
	if( isset($_GET['mode']) )
	{
		$mode = $_GET['mode'];
	}

	//display project managers
	$project_managers = $dashboard->get_project_managers($project_id, true);
	
	if( isset($_GET['id']) )
	{
		$project_id = (int) $_GET['id'];

		//overwrite global user AUTH_OPTIONS for AUTH_OPTIONS_PROJECT
		foreach($auth->auth_options_project[$project_id] as $key => $value)
		{
			$template->assign(array(
				strtoupper($key) =>	$value == 1 ? true : false,
			));
		}
		
		/**
		 *	If a user is specified, we're trying to edit user permissions
		 *	for that user_id and this project_id.
		 */
		 
		if( isset($_GET['u']) && is_numeric($_GET['u']) )
		{
			$auth_user_id = (int) $_GET['u'];
			$show_auth_form = true;
			
			$user_row = $dashboard->user_details($auth_user_id);
			
			$template->assign(array(
				'PROJECT_USER_REALNAME'		=>	$user_row['user_realname'],
				'PROJECT_USER_ID'			=>	$auth_user_id,
			));
			
			//display permissions for this user
			$auth->mypermissions($auth_user_id, $project_id);

		}

		$projectrow = $dashboard->get_project_detail($project_id);

		foreach($project_managers as $pm_user_id => $pm_user_realname)
		{
			if( $user_id == $pm_user_id )
			{
				$s_project_manager = true;
			}
		}
		
		$project_name = litwicki_decode($projectrow['project_name']);
		
		$assigned_users = $dashboard->get_assigned_users('project', $project_id);
		$s_assigned_user = (in_array($user_id, $assigned_users) ? true : false);
		
		//display all users with assigned ones selected
		$dashboard->get_project_userlist($project_id);
		
		$show_detail = ((is_array($projectrow)) ? true : false);

		//get messages for this project
		$message_count = $dashboard->mymessages($user_id, $project_id);
		
		//get all tasks for this project
		$task_count = $dashboard->mytasks($user_id, $project_id);

		/**
		 *	Get the proposal for this project if there is one!
		 *	FALSE if there is no active proposal.
		 */

		$proposal_row = $dashboard->get_project_proposal($project_id);
	
		if($proposal_row)
		{
			//get attachments for proposal
			$attachrow = $dashboard->get_attachments($proposal_row['message_id'], 1, true);
			
			$proposal_status = $proposal_row['status'];
			
			$template->assign(array(
				'PROPOSAL_TITLE'			=>	litwicki_decode($proposal_row['subject']),
				'PROPOSAL_DESCRIPTION'		=>	litwicki_decode($proposal_row['message']),
				'SHOW_ATTACHMENTS'			=>	count($attachrow) > 0 ? true : false,
				'SHOW_PROJECT_PROPOSAL'		=>	($proposal_status == 1 || $proposal_status == 2) ? true : false,
			));
		}
		
		//is this a valid project?
		if(!$show_detail)
		{
			//invalid project_id, so display a full list with an error
			$project_count = $dashboard->myprojects($user_id);
			$form_error = "Invalid Project Id Specified!";
			$show_form = false;
		}

		if( $mode == "edit" )
		{
			if( ($auth->options['U_EDIT_PROJECT'] && $auth->user_group['S_STAFF']) || $auth->user_group['S_ADMINISTRATOR'] )
			{
				$page_title = "Edit Project: $project_name";
			}
			else
			{
				$form_error = 'You are not authorized to edit projects!';
				$project_count = $dashboard->myprojects($user_id);
				$page_title = "My Projects ($project_count)";
			}
		}
		else
		{
			$page_title = "$project_name - $task_count Task".(($task_count==1) ? "" : "s").", $message_count Message".(($message_count==1) ? "" : "s");
		}
		
		$show_form = (($mode == "edit" || $mode == "complete") ? true : false);
	}
	else
	{
		if( $mode == "add" )
		{
			if( ($auth->options['U_ADD_PROJECT'] && $auth->user_group['S_STAFF']) || $auth->user_group['S_ADMINISTRATOR'] )
			{
				$page_title = "Add New Project";
				
				//display all users with assigned ones selected
				$dashboard->get_userlist($staff_only = true);
			}
			else
			{
				$project_count = $dashboard->myprojects($user_id);
				$page_title = "My Projects ($project_count)";
				$form_error = 'You are not authorized to add a new project!';
			}
		}
		else
		{
			$project_count = $dashboard->myprojects($user_id);
			$page_title = "My Projects ($project_count)";
		}
		
		$show_form = $mode == "add" ? true : false;
		
	}
	
	/**
	 *	Specify a project_id to auto-select the client_id associated to that project
	 *	within the clientlist <select> If there is no project_id we just won't have
	 *	the client_id auto-selected which is annoying when editing a project.
	 */
	$clientlist = $dashboard->get_clientlist($project_id, $user_id = false);

}

//global defaults
$template->assign(array(
	'S_PROJECT'				=>	true,
	'SUCCESS_LINK'			=>	$success_link,
	'PROJECT_ERROR'			=>	$form_error,
	
	'S_USER_ACCESS'			=>	$s_assigned_user ? true : ($project_row['user_id'] == $user_id) ? true : false,
	 
	'MESSAGE_COUNT'			=>	$message_count,
	'SHOW_MESSAGELIST'		=>	(($message_count > 0) ? true : false),
	'NO_MESSAGES'			=>	'There are no messages linked to this project.',
	
	'TASK_COUNT'			=>	$task_count,
	'SHOW_TASKLIST'			=>	(($task_count > 0) ? true : false),
	'NO_TASKS'				=>	'There are no tasks linked to this project.',
	
	'PROJECT_COUNT'			=>	$project_count,
	'SHOW_PROJECTLIST'		=>	(($project_count > 0) ? true : false),
	'MANAGER_COUNT'			=>	count($project_managers),
	'S_PROJECT_MANAGER'		=>	$s_project_manager,
	
	'SHOW_DETAIL'			=>	$show_detail,
	'SHOW_FORM'				=>	$show_form,
	'SHOW_AUTH_FORM'		=>	$show_auth_form,
	'FORM_NAME'				=>	$page_title,
	'NO_PROJECTS'			=>	"No projects available!",
	'S_DASHBOARD_PAGE'		=>	true,
));

//spit out the page
page_header($page_title);

$template->display('template/dashboard_project_body.html');

page_footer();
?>
