<?php 

/****
 *
 *  @author: jake@litwickimedia.com
 *  @SVN: $Id: tasks.php 40 2010-06-03 05:16:22Z jake $
 *  @Copyright 2009,2010 $company_name LLC
 *
 ***/
 
define('MY_DASHBOARD', true);
$root_path = './';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($root_path . 'common.' . $phpEx);
include($root_path . 'includes/invoice.' . $phpEx);

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

$company_name = $config['company_name'];

//saving a task
if( isset($_POST['save']) )
{

	$task_name 			=	litwicki_cleanstr($_POST['task_name']);
	$task_description	=	litwicki_cleanstr($_POST['task_description']);
	$due_date			=	strtotime($_POST['due_date']);
	
	$project_id 		=	(int) $_POST['project_id'];
	$projectrow 		=	$dashboard->get_project_detail($project_id);
	$project_name 		=	$projectrow['project_name'];
	
	$task_row = array(
		'start_date'		=>	time(),
		'due_date'			=>	$due_date,
		'task_name'			=>	$task_name,
		'task_description'	=>	$task_description,
		'project_id'		=>	$project_id,
		'user_id'			=>	$user_id,
		'status_date'		=>	time(),
	);

	$task_id = (int) $_POST['task_id'];
	
	//if task_id is 0, we're adding a new task
	if($task_id === 0)
	{
		//task_id is returned when a new one is created
		//use this task_id to redirect below
		$task_id = $dashboard->add_task($task_row);
	}
	else
	{
		//task_id is not 0, so we're updating a specific task
		$dashboard->modify_task($task_id, $task_row);
	}
	
	/**
	 *	Manage the users assigned to the task.
	 */
	 
	//first get the current users
	$assigned_users = $dashboard->get_assigned_users('task', $task_id, $output = false);
	
	//$_POST['selected_users'] is an array from task_form.html
	$selected_users = $_POST['selected_users'];

	$dashboard->manage_user_assignments('task', $task_id, $selected_users, $assigned_users);
	
	$json_array = array(
		'task_name'		=>	$task_name,
		'task_id'		=>	$task_id,
		'project_id'	=>	$project_id,
		'project_name'	=>	$project_name,
	);

	$json = json_encode($json_array);
	echo $json;
	
	exit;
	
}
//deleting a project (really just changing its status)
elseif( isset($_POST['delete']) )
{
	$task_id = $_POST['task_id'];
	$dashboard->change_status('task', $task_id, $delete_status);
	redirect("$base_url/");
}
//completing a task for client approval
elseif( isset($_POST['complete']) )
{
	$task_id = $_POST['task_id'];
	$dashboard->change_status('task', $task_id, $complete_status);
	
	$taskrow = $dashboard->get_task_detail($task_id);
	
	$project_id = $taskrow['project_id'];
	$projectrow = $dashboard->get_project_detail($project_id);
	$client_id = $projectrow['client_id'];
	
	$taskname = $taskrow['task_name'];
	$owner_user_id = $taskrow['user_id'];
	
	$subject = "$company_name - Task Complete: $taskname";
	$message = "$username completed task ($taskname): $base_url/tasks.php?id=$task_id\n\nPlease review the time log and task notes and confirm completion or provide feedback for continued work to be completed.";
	
	//email client that the task was finished
	//and is now pending client approval
	$dashboard->dashboard_email($subject, $message, $client_id, $html_email = false, $priority = 3, $attachments = false);
	
	//notify staff
	
	$staff_subject = "$username completed task: $taskname";
	$staff_message = "$username completed task: $taskname\n\nView task details: $base_url/tasks.php?id=$task_id" . $email_signature;
	
	notify_staff($staff_subject, $staff_message, $priority = 3);
	
	redirect("$base_url/tasks.php?id=$task_id");
}
elseif( isset($_POST['approve']) )
{
	$task_id = $_POST['task_id'];
	
	$project_id = $taskrow['project_id'];
	$projectrow = $dashboard->get_project_detail($project_id);
	$client_id = $projectrow['client_id'];
	
	$dashboard->change_status('task', $task_id, $pending_status);
	
	$task_approve_row = array(
		'task_id'			=>	$task_id,
		'user_id'			=>	$user_id,
		'user_ip'			=>	$_SERVER["REMOTE_ADDR"],
		'user_agent'		=>	$_SERVER["HTTP_USER_AGENT"],
		'session_id'		=>	$user->data['session_id'],
		'approval_notes'	=>	litwicki_cleanstr($_POST['client_notes']),
		'date_approved'		=>	time(),
	);
	
	//log all the user info so we have proof they approved it
	$dashboard->approve_task($task_approve_row);
	
	//email staff that the completed work was APPROVED by the client, huge deal!
	$taskrow = $dashboard->get_task_detail($task_id, false);
	$taskname = $taskrow['task_name'];
	
	unset($taskrow);
	
	$username = $user->data['user_realname'];
	$approval_notes = $_POST['client_notes'];
	
	$subject = "$company_name - Finished task approved: $taskname";
	$message = "$username just approved the completed task ($taskname): $base_url/tasks.php?id=$task_id\n\nNotes: $approval_notes";
	
	$dashboard->dashboard_email($subject, $message, $client_id, $html_email = false, $priority = 3, $attachments = false);
	notify_staff($subject, $message, $priority = 5);
	
	redirect("$base_url/");
}
elseif( isset($_POST['reopen']) || isset($_POST['staff_reopen']) )
{
	$task_id = $_POST['task_id'];
	$approval_notes = litwicki_cleanstr($_POST['client_notes']);
	$username = $user->data['username'];
	
	$taskrow = $dashboard->get_task_detail($task_id, false);
	$taskname = $taskrow['task_name'];
	$client_notes = litwicki_cleanstr($_POST['client_notes']);
	
	if( isset($_POST['reopen']) )
	{
		$email_subject = "$company_name - Task Declined: $taskname";
		$message = "$username marked task ($taskname) unsatisfactory: $base_url/tasks.php?id=$task_id\n\nClient Notes: $decline_notes\n\n";
		$email_priority = 5;
	}
	else
	{
		$email_subject = "$company_name - Task Reopened: $taskname";
		$message = "Task ($taskname) reopened by $username.";
	}
	
	$email_message = "$message \n\n $client_notes";
	
	notify_staff($email_subject, $email_message, $priority = 4);
	
	$openrow = array(
		'task_id'			=>	(int) $_POST['task_id'],
		'approval_notes'	=>	$approval_notes,
		'email_subject'		=>	$email_subject,
		'email_message'		=>	$email_message,
		'email_priority'	=>	$email_priority,
	);
	
	unset($taskrow);
	
	$dashboard->open_task($openrow);
	redirect("$base_url/tasks.php?id=$task_id");
}
elseif( isset($_POST['savenote']) )
{
	$task_id = (int) $_POST['task_id'];
	$message = sanitize($_POST['message']);
	$subject = sanitize($_POST['subject']);
	
	$message_row = array(
		'subject'		=>	$subject,
		'message'		=>	$message,
		'date_added'	=>	time(),
		'status_date'	=>	time(),
		'user_id'		=>	$user_id,
	);
	
	$message_id = $dashboard->add_message($message_row);
	
	$dashboard->add_task_message($task_id, $message_id, $log = true);
	
	$json = json_encode($message_row);
	echo $json;
	exit;
	
}
elseif( isset($_POST['deletenote']) )
{
	
}
else
{
	/*
	* Are we trying to add, edit, or view a proposal?
	*/
	if( isset($_GET['mode']) )
	{
		if( $_GET['mode'] == "add" || $_GET['mode'] == "edit" )
		{
			$mode = $_GET['mode'];
		}
	}

	if( isset($_GET['id']) )
	{	
		$task_id = (int) $_GET['id'];
		$taskrow = $dashboard->get_task_detail($task_id);
		
		//get the list of users and flag ones that are assigned
		$dashboard->get_task_userlist($task_id);
				
		$project_id = $taskrow['project_id'];
		$projectrow = $dashboard->get_project_detail($project_id, false);
		$project_name = $projectrow['project_name'];
		
		$authorized_user = $auth->auth_options['U_EDIT_TASK'] || $auth->user_group['S_ADMINISTRATOR'] ? true : $dashboard->task_assigned($user_id, $task_id);
		
		$show_detail = ( ( is_array($taskrow) ) ? true : false);
		
		$task_name = $taskrow['task_name'];
		$page_title = $authorized_user ? "Task Details ($task_name)" : "Cannot Access Task Details";
		
		/*
		 *	If this task status == 2, this task was approved, 
		 *	by the client, so get the name of the user who signed off
		 *	so we can display it.
		 */
		
		if($taskrow['status'] == 2)
		{
			$sql = "SELECT user_id FROM ".APPROVED_TASKS_TABLE." WHERE task_id=$task_id";
			$result = $db->sql_query($sql);
			$row = $db->sql_fetchrow($result);
			
			$template->assign(array(
				'USER_APPROVED_NAME'	=>	get_user_val("user_realname", (int) $row['user_id']),
			));
			
			unset($sql);
			unset($row);
			
		}

		//is this a valid project?
		if(!$show_detail)
		{
			//invalid task_id, so display a full list with an error
			$task_count = $dashboard->mytasks($user_id);
			$form_error = "Invalid Task Id Specified!";
			$show_form = false;
		}

		if( $mode == "edit" )
		{
			if( ($auth->auth_options['U_EDIT_TASK'] && $auth->user_group['S_STAFF']) || $auth->user_group['S_ADMINISTRATOR'] )
			{
				$show_form = true;
				$show_task_form = true;
				$page_title = "Edit Task ($task_name)";
			}
			else
			{
				$form_error = 'You are not authorized to edit this task!';
			}
		}
	}
	else
	{
		if( $mode == "add" )
		{
			if( ($auth->auth_options['U_ADD_TASK'] && $auth->user_group['S_STAFF']) || $auth->user_group['S_ADMINISTRATOR'] )
			{
				$show_form = true;
				$show_task_form = true;
				$page_title = "Add New Task";

				$project_id = (int) $_GET['p'];
				$project_row = $dashboard->get_project_detail($project_id);
				$dashboard->get_projectlist('task');

				//get a list of all users
				$dashboard->get_userlist();
			}
			else
			{
				$form_error = 'You are not authorized to add a new task!';
				$task_count = $dashboard->mytasks($user_id);
				$page_title = "My Tasks ($task_count)";
			}
		}
		else
		{
			//are we viewing tasks for a specific project?
			if($project_id)
			{
				$task_count = $dashboard->mytasks($user_id, $project_id);
				$page_title = "Tasks for $project_name ($task_count)";
			}
			else
			{
				$task_count = $dashboard->mytasks($user_id);
				$page_title = "My Tasks ($task_count)";
			}
		}
	}
}

//global defaults
$template->assign(array(
	'S_DASHBOARD_PAGE'		=>	true,
	'S_TASK'				=>	true,
	'SUCCESS_MESSAGE'		=>	$success_message,
	'SUCCESS_LINK'			=>	$success_link,

	'TASK_COUNT'			=>	$task_count,
	'SHOW_TASKLIST'			=>	(($task_count > 0) ? true : false),
	'NO_TASKS'				=>	'You are not assigned to any tasks.',

	'SHOW_DETAIL'			=>	$show_detail,
	'SHOW_FORM'				=>	$show_form,
	
	//Don't show the task form if there are no projects
	'SHOW_TASK_FORM'		=>	$show_task_form,
	
	'TASK_ERROR'			=>	$form_error,
	'TASK_ID'				=>	$task_id,
	'FORM_NAME'				=>	$page_title,
	
	'TODAY'					=>	date('m/d/Y', time()),
	'STATUS_DATE'			=>	(($taskrow['status'] == 2 && $taskrow['status_date']) ? date($config['date_long'], $taskrow['status_date']) : false),
	'S_CLIENT_COMPLETE'		=>	(($taskrow['status'] == 2) ? true : false),
	'S_STAFF_COMPLETE'		=>	(($taskrow['status'] == 3) ? true : false),
	
	'S_USER_ACCESS'			=>	$authorized_user,
	'ASSIGNED_USER'			=>	$dashboard->task_assigned($user_id, $task_id),
	
));

//spit out the page
page_header($page_title);

$template->display('template/dashboard_task_body.html');

page_footer();
?>
