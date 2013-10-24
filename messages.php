<?php

/****
 *
 *  @author: jake@litwickimedia.com
 *  @SVN: $Id: messages.php 41 2010-06-04 16:56:54Z jake $
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

$company_name = $config['company_name'];
$email_signature = $config['email_signature'];

if( !$user_id )
{
	login_box("$base_url");
}

//setup user permissions
$auth->setup($user_id);

//build user dashboard
$dashboard = new dashboard();
$dashboard->setup($user_id);

if( isset($_POST['subject']) )
{
	$project_id 		= (int) $_POST['project_id'];
	$subject 			= sanitize($_POST['subject']);
	$message 			= sanitize($_POST['message']);
	$message_user_id 	= (int) $_POST['message_user_id'];
	
	/**
	 *	If a message_user_id is specified, this message
	 *	is intended for one user only (message_user_id).
	 *	
	 *	If a project_id is specified, this message
	 *	is intended for all users of a project (project_id).
	 */
	
	//if a message_id is specified, modify rather than add
	$message_id 		= (int) $_POST['message_id'];
	
	$message_row = array(
		'subject'		=>	$subject,
		'message'		=>	$message,
		'user_id'		=>	$user_id,
		'date_added'	=>	time(),
		'status_date'	=>	time(),
	);

	if( $message_id === 0 )
	{
		$message_id = $dashboard->add_message($message_row);

		/**
		 *	We only need to 'link' the message appropriately when we first
		 *	add it to the database. After that, we don't allow the user to
		 *	modify the project_id or message_user_id so it will never change
		 *	by design, but we still want to give the author the ability to
		 *	edit the message if needed.
		 */
		 
		if($message_user_id)
		{
			$message_link = $base_url.'/messages.php?id='.$message_id;
			$dashboard->add_user_message($message_user_id, $message_id);
			$email_message = "$username sent you a new message: $message_link\n\n";
		}
		else
		{
			$message_link = $base_url.'/messages.php?id='.$message_id;
			$project_row 	= $dashboard->get_project_detail($project_id, false);
			$project_name 	= $project_row['project_name'];
			$client_user_id = $project_row['user_id'];
			
			$dashboard->add_project_message($project_id, $message_id);
			$email_message = "$username posted a new message for your project ($project_name)\nView: $message_link\n\n";
		}
		
		$email_subject = "$company_name - New Message!";
		
	}
	else
	{
		$dashboard->modify_message($message_id, $message_row);
		$email_subject = "$company_name - Message ($subject) Updated!";
		$email_message = "$username updated message: $subject\nView: $message_link\n\n";
	}

	/**
	 *	Do not send the attachment via email!
	 *	Just notify the owner of the project_id associated
	 *	to this message_id that there is a new message.
	 */
	 
	if( !empty($_FILES['attachments']))
	{			
		$attachments = $_FILES['attachments'];
		//attach these messages to this message (proposal) and take the attachment_id() array for emails
		$attachment_ids = $dashboard->message_attach($attachments, $message_id);
	}

	$message_link = $base_url.'/messages.php?id='.$message_id;
	
	if($message_user_id)
	{
		email_user($email_subject, $email_message, $message_user_id, $html_email = false, $priority = 3);
	}
	else
	{
		$dashboard->dashboard_email($email_subject, $email_message, $client_id, $html_email = false, $priority = 3);
	}
	
	$response = array(
		'item_id'			=>	$message_id,
		'item_link'			=>	$message_link,
		'item_type'			=>	'Message',
		'item_subject'		=>	$subject,
	);
	
	$json = json_encode($response);
	echo $json;
	exit;
	
	//redirect("$base_url/messages.php?id=$message_id");
}
elseif( isset($_POST['read']) )
{
	$message_id = $_POST['message_id'];
	$dashboard->mark_message_read($message_id, $user_id);
	redirect($base_url);
}
elseif( isset($_POST['unmark']) )
{
	$message_id = $_POST['message_id'];
	$dashboard->mark_message_unread($message_id);
	redirect($base_url);
}
elseif( isset($_POST['delete']) )
{
	$message_id = (int) $_POST['message_id'];
	$dashboard->change_status('message', $message_id, $closed_status);
	exit;
}
else
{
	/*
	* Are we trying to add, edit, or view a message?
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
		$message_id = $_GET['id'];
		
		//confirm this message has no parent_id
		$sql = "SELECT * FROM ".REPLIES_TABLE." WHERE message_id=$message_id";
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrow($result);
		$parent_id = $row['parent_id'];
		
		//get the parent_id details if there is one, otherwise get the specified message_id
		$get_message_id = (($parent_id) ? $parent_id : $message_id);
		
		$message_row = $dashboard->get_message_detail($get_message_id);
		$project_id = $message_row['project_id'];
		$message_subject = $message_row['subject'];
		
		//display project info too so we know what this message is related to..
		$dashboard->get_project_detail($project_id);
		
		$page_title = "Message: $message_subject";
		$show_detail = ((is_array($message_row)) ? true : false);
		
		//is this a valid message_id?
		if(!$show_detail)
		{
			//invalid proposal_id, so display a full list with an error
			$message_count = $dashboard->mymessages($user_id);
			$form_error = "Invalid Message Id Specified!";
			$show_form = false;
		}
		
		if( $mode == "edit" )
		{
			if( ($auth->auth_options['U_ADD_MESSAGE'] && $auth->user_group['S_STAFF']) || $auth->user_group['S_ADMINISTRATOR'] )
			{
				$show_form = true;
				$page_title = "Edit Message: $message_subject";
				
				//show the messagelist and select the current project_id	
				$dashboard->get_projectlist($user_id, $project_id);
			}
			else
			{
				$error = 'You are not authorized to edit this message!';
			}
		}
	}
	else
	{
		if( $mode == "add" )
		{
			if( ($auth->auth_options['U_ADD_MESSAGE'] && $auth->user_group['S_STAFF']) || $auth->user_group['S_ADMINISTRATOR'] )
			{
				$show_form = true;
				$page_title = "Add New Message";
				$show_pending = false;
				$project_id = ((isset($_GET['p'])) ? (int) $_GET['p'] : false);

				/**
				 *	Do not assign below and accidentally overwrite project_id of an existing message
				 *	because we do not allow users to change the project a message is linked to when they edit
				 */
				$template->assign(array(
					'PROJECT_ID'	=>	$project_id,
				));
				
				//show the projectlist, but we don't need to auto-select a project
				$dashboard->get_projectlist('message', $user_id, $project_id, $show_pending);
			}
			else
			{
				$error = 'You are not authorized to add a new message!';
			}
		}
		else
		{
			//show ALL messages
			$message_count = $dashboard->mymessages($user_id, false, true);
			$page_title = "My Messages ($message_count)";
		}
	}
}

$my_project_count = $dashboard->myprojects($user_id, false);

//global defaults
$template->assign(array(
	'S_MESSAGE'				=>	true,
	'S_STAFF'				=>	is_staff($user_id) ? true : false,
	'S_CLIENT'				=>	is_client($user_id) ? true : false,
	'SUCCESS_MESSAGE'		=>	$success_message,
	'SUCCESS_LINK'			=>	$success_link,
	'MESSAGE_COUNT'			=>	$message_count,
	'SHOW_DETAIL'			=>	$show_detail,
	'SHOW_MESSAGELIST'		=>	$message_count > 0 ? true : false,
	'SHOW_FORM'				=>	$show_form,
	'SHOW_MESSAGE_FORM'		=>	$auth->user_group['S_ADMINISTRATOR'] || $my_project_count > 0 ? true : false,
	'MESSAGE_ERROR'			=>	$error,
	'MESSAGE_ID'			=>	$message_id,
	'S_MESSAGE_AUTHOR'		=>	(($message_row['user_id'] == $user_id) ? true : false),
	'FORM_NAME'				=>	$page_title,
	'NO_MESSAGES'			=>	'No messages available!',
	'S_DASHBOARD_PAGE'		=>	true,
	'S_AUTHORIZED_USER'		=>	$auth->options['U_ADD_MESSAGE'] || $auth->options['U_EDIT_MESSAGE'] ? true : false,
));

//spit out the page
page_header($page_title);

$template->display('template/dashboard_message_body.html');

page_footer();
?>
