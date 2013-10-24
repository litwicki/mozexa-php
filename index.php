<?php

/****
 *
 *  @author: jake@litwickimedia.com
 *  @SVN: $Id: index.php 41 2010-06-04 16:56:54Z jake $
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

//new invoice class
$invoice = new invoice();

//get proposal requests
$request_count 	= $dashboard->myrequests($user_id);
$proposal_count = $dashboard->myproposals($user_id);
$project_count 	= $dashboard->myprojects($user_id);
$task_count		= $dashboard->mytasks($user_id);
$message_count	= $dashboard->mymessages($user_id);
$invoice_count	= $invoice->myinvoices($paid_status = 0, $project_id = false);
$client_count	= $dashboard->myclients();

//build userlist
$dashboard->get_userlist();

//build list of companies
//$client_count = $dashboard->get_clientlist($project_id = false, $user_id = false, $output = true);

$template->assign(array(
	'S_HOME_PAGE'			=>	true,
	'SHOW_REQUESTLIST'		=>	(($request_count) ? true : false),
	'REQUEST_COUNT'			=>	$request_count,
	'NO_REQUESTS'			=>	'There are no pending proposal requests.',
	
	'SHOW_PROPOSAL_LIST'	=>	(($proposal_count) ? true : false),
	'PROPOSAL_COUNT'		=>	$proposal_count,
	'NO_PROPOSALS'			=>	$auth->user_group['S_STAFF'] ? 'No project proposals found.' : 'None of your projects have a proposal.',
	
	'SHOW_MESSAGELIST'		=>	(($message_count) ? true : false),
	'MESSAGE_COUNT'			=>	$message_count,
	'NO_MESSAGES'			=>	'You have no messages.',
	
	'SHOW_TASKLIST'			=>	(($task_count) ? true : false),
	'TASK_COUNT'			=>	$task_count,
	'NO_TASKS'				=>	$auth->user_group['S_STAFF'] ? 'You are not assigned to any incomplete tasks.' : 'There are no active tasks for your project(s).',
	
	'SHOW_PROJECTLIST'		=>	(($project_count) ? true : false),
	'PROJECT_COUNT'			=>	$project_count,
	'NO_PROJECTS'			=>	$auth->user_group['S_STAFF'] ? 'You are not assigned to any projects.' : 'You have no active projects.',
	
	'INVOICE_COUNT'			=>	$invoice_count,
	'NO_INVOICES'			=>	$auth->user_group['S_STAFF'] ? 'There are no invoices available.' : 'You have no unpaid invoices.',
	
	'CLIENT_COUNT'			=>	(int) $client_count,
	'NO_CLIENTS'			=>	'No clients found.',

	'SHOW_USER_LOG'			=>	$show_user_log,
	'SHOW_CLEANUP_WARNING'	=>	((file_exists($cleanup_file)) ? true : false),
));

//spit out the page
page_header("My Dashboard");

//output the page
$template->display($root_path . 'template/dashboard_body.html');

page_footer();

?>