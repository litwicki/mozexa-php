<?php

/****
 *
 *  @author: jake@litwickimedia.com
 *  @SVN: $Id: proposals.php 38 2010-06-02 02:52:38Z jake $
 *  @Copyright 2009,2010 Litwicki Media LLC
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

//add a new proposal
if( isset($_POST['subject']) )
{
	$project_id 		= (int) $_POST['project_id'];
	$subject 			= sanitize($_POST['subject']);
	$message 			= sanitize($_POST['message']);
	$user_id 			= (int) $user->data['user_id'];
	
	$proposal_id 		= (int) $_POST['proposal_id'];
	$message_id			= (int) $_POST['message_id'];

	//build proposal array
	$proposal_row = array(
		'project_id'	=>	(int) $_POST['project_id'],
		'subject'		=>	$subject,
		'message'		=>	$message,
		'author_id'		=>	$user_id,
		'status_date'	=>	time(),
	);
	
	//build email
	//get user_id of the project owner so we know who to send the email to
	$projectrow = $dashboard->get_project_detail($project_id);
	$client_user_id = $projectrow['user_id'];
	$client_id = $projectrow['client_id'];
	$company_name = $projectrow['company_name'];
	unset($projectrow);
	
	if($proposal_id === 0)
	{
		$proposal_id = $dashboard->add_proposal($proposal_row);
		$proposal_link = "$base_url/proposals.php?id=$proposal_id";
		
		//email the client they have a proposal to review!
		$subject = "$company_name - Proposal Ready for Review: $proposal_subject";
		$message = "$username submitted a proposal for your review. Please feel free to review it online and leave any feedback you have.\n\n$proposal_link\n\n";

	}
	else
	{
		$proposal_row['message_id'] = $message_id;
		$proposal_row['proposal_id'] = $proposal_id;
		$dashboard->modify_proposal($proposal_row);
		
		$proposal_link = "$base_url/proposals.php?id=$proposal_id";
		
		//email the client
		$subject = "$company_name - Proposal Updated: $proposal_subject";
		$message = "$username updated proposal: $proposal_subject\nAt your convenience, please review the changes and leave us any additional feedback you may have. The proposal is also attached for your review.\n\n$proposal_link\n\n";
	}

	/**
	 *	We only want to attach NEW attachments to the email.
	 *	Other files will be available on the website, but
	 *	we don't want to end up sending dozens of files via
	 *	email to the client.
	 */
	 
	if( !empty($_FILES['attachments']))
	{			
		$proposal = $dashboard->get_proposal_detail($proposal_id);
		$message_id = $proposal['message_id'];
		
		$attachments = $_FILES['attachments'];

		//attach these messages to this message (proposal) and take the attachment_id() array for emails
		$attachment_ids = $dashboard->message_attach($attachments, $message_id);
	}
	
	$dashboard->dashboard_email($subject, $message, $client_id, $html_email = false, $priority = 4, (empty($attachment_ids) ? false : $attachment_ids) );
	//email_user($subject, $message, $client_user_id, $html_email = false, $priority = 4, (empty($attachment_ids) ? false : $attachment_ids) );

	$response = array(
		'item_id'			=>	$proposal_id,
		'item_link'			=>	$proposal_link,
		'item_type'			=>	'Proposal',
		'item_subject'		=>	$subject,
	);
	
	$json = json_encode($response);
	echo $json;
	exit;
	
	//redirect("$base_url/proposals.php?id=$proposal_id");

}
//approve the proposal
elseif( isset($_POST['approve']) )
{
	$proposal_id = $_POST['proposal_id'];
	$dashboard->approve_proposal($proposal_id);
	
	$proposal_row = $dashboard->get_proposal_detail($proposal_id, false);
	$proposal_name = $proposal_row['subject'];
	
	$message = "$user_realname approved a project proposal!\nView proposal: $base_url/proposals.php?id=$proposal_id\n\n";
	
	notify_staff("Proposal Approved: $proposal_name", $message);

	redirect($base_url);
}
elseif( isset($_POST['decline']) )
{
	$proposal_id = $_POST['proposal_id'];
	$dashboard->decline_proposal($proposal_id);
	
	$proposal_row = $dashboard->get_proposal_detail($proposal_id, false);
	$client_name = $proposal_row['client_name'];
	$proposal_name = $proposal_row['subject'];
	
	$feedback = $_POST['feedback'];
	$proposal_link = '<a href="'.$base_url.'/proposals.php?id='.$proposal_id.'">'.$proposal_name.'</a>';
	
	$message = "$user_realname <strong>declined</strong> proposal: $proposal_link\n\nComments: $feedback";
	
	$html_email = true;
	$attachments = false;
	
	notify_staff("Proposal Declined: $proposal_name", $message, $html_email, $attachments);

	redirect($base_url);
	
}
//delete the proposal
elseif( isset($_POST['delete']) )
{
	$proposal_id = $_POST['proposal_id'];
	$dashboard->change_status('proposal', $proposal_id, $delete_status);
	
	$proposal_row = $dashboard->get_proposal_id($proposal_id);
	$request_id = $proposal_row['request_id'];
	
	//reopen the initial request if there is one
	//TODO: verify there is a request before changing status here
	$dashboard->change_status('request', $request_id, $open_status);
	redirect("$base_url/");
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
		$proposal_id = (int) $_GET['id'];
		$proposal_row = $dashboard->get_proposal_detail($proposal_id);
		$project_id = $proposal_row['project_id'];
		$message_id = $proposal_row['message_id'];
		
		//display project info too so we know what this proposal is related to..
		$dashboard->get_project_detail($project_id);
		
		$page_title = "View Proposal (" . $proposal_row['subject'] . ")";
		$show_detail = ((is_array($proposal_row)) ? true : false);
		
		//is this a valid proposal?
		if(!$show_detail)
		{
			//invalid proposal_id, so display a full list with an error
			$proposal_count = $dashboard->myproposals($user_id);
			$form_error = "Invalid Proposal Id Specified!";
			$show_form = false;
			$no_proposals = $form_error;
		}
		
		if( $mode == "edit" )
		{
			$page_title = "Edit Proposal";
			
			if( ($auth->auth_options['U_EDIT_PROPOSAL'] && $auth->user_group['S_STAFF']) || $auth->user_group['S_ADMINISTRATOR'] )
			{
				$show_form = true;
				get_projectlist('proposal', $project_id);
			}
			else
			{
				$form_error = 'You are not authorized to edit this proposal!';
			}
		}
	}
	else
	{
		if( $mode == "add" )
		{
			$page_title = "Add New Proposal";
			
			if( ($auth->auth_options['U_ADD_PROPOSAL'] && $auth->user_group['S_STAFF']) || $auth->user_group['S_ADMINISTRATOR'] )
			{
				$show_form = true;
				$project_id = false;
				
				//if a request_id is specified, get the associated project
				if( isset($_GET['rid']) )
				{
					$request_id = $_GET['rid'];
					$sql = "SELECT project_id FROM ".REQUESTS_TABLE." WHERE request_id=$request_id";
					$result = $db->sql_query($sql);
					$row = $db->sql_fetchrow($result);
					$project_id = $row['project_id'];
				}
				elseif( isset($_GET['p']) )
				{
					$project_id = (int) $_GET['p'];
				}
				
				//show the projectlist, but we don't need to auto-select a project
				$dashboard->get_projectlist('proposal', $project_id);
			}
			else
			{
				$form_error = 'You are not authorized to add a new proposal!';
			}
		}
		else
		{
			$proposal_count = $dashboard->myproposals($user_id);
			$page_title = "My Proposals ($proposal_count)";
			$no_proposals = "No proposals found!";
		}
	}
}

$my_request_count = $dashboard->myrequests($user_id, false);
$my_project_count = $dashboard->myprojects($user_id, false);

$show_message_form = ($my_request_count > 0 || $show_detail == true || $my_project_count > 0 || $project_id > 0 ? true : false);

//global defaults
$template->assign(array(
	'S_PROPOSAL'			=>	true,
	'S_STAFF'				=>	is_staff($user_id) ? true : false,
	'S_CLIENT'				=>	is_client($user_id) ? true : false,
	'SUCCESS_MESSAGE'		=>	$success_message,
	'SUCCESS_LINK'			=>	$success_link,
	'PROPOSAL_COUNT'		=>	$proposal_count,
	'SHOW_DETAIL'			=>	$show_detail,
	'SHOW_PROPOSAL_LIST'	=>	(($proposal_count > 0) ? true : false),
	
	//show the edit form, or display details?
	'SHOW_FORM'				=>	$show_form,
	
	//if there are no requests, do not allow a user to add an orphan proposal
	'SHOW_MESSAGE_FORM'		=>	$show_message_form,
	'FORM_NAME'				=>	$page_title,
	'FORM_ERROR'			=>	$form_error,
	
	'PROPOSAL_ID'			=>	$proposal_id,
	'NO_PROPOSALS'			=>	$no_proposals,
	'S_DASHBOARD_PAGE'		=>	true,
	'PROPOSAL_APPROVED'		=>	$proposal_row['proposal_status'] == 2 ? true : false,
	'S_AUTHORIZED_USER'		=>	$auth->options['U_ADD_PROPOSAL'] || $auth->options['U_EDIT_PROPOSAL'] ? true : false,
));

//spit out the page
page_header($page_title);

$template->display('template/dashboard_message_body.html');

page_footer();
?>