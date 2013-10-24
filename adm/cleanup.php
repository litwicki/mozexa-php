<?php 

/****
 *
 *  @author: jake@litwickimedia.com
 *  @SVN: $Id$
 *  @Copyright 2009,2010 Litwicki Media LLC
 *
 ***/
 
DEFINE('MY_DASHBOARD', true);
$root_path = '../';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($root_path . 'common.' . $phpEx);

$table_array = array(
	AUTH_GROUPS_TABLE,
	AUTH_USERS_TABLE,
	LOG_TABLE,
	SESSIONS_TABLE,
	CLIENTS_TABLE,
	CLIENT_USERS_TABLE,
	PROJECTS_TABLE,
	PROJECT_LOG_TABLE,
	PROJECT_USERS_TABLE,
	APPROVED_PROJECTS_TABLE,
	TASKS_TABLE,
	TASK_USERS_TABLE,
	TASK_TIMELOG_TABLE,
	APPROVED_TASKS_TABLE,
	MILESTONES_TABLE,
	MILESTONE_TASKS_TABLE,
	MESSAGES_TABLE,
	READ_MESSAGES_TABLE,
	REPLIES_TABLE,
	REQUESTS_TABLE,
	PROPOSALS_TABLE,
	ATTACHMENTS_TABLE,
	INVOICES_TABLE,
	INVOICE_HOURS_TABLE,
	INVOICE_RECURRENCE_TABLE,
);

foreach($table_array as $table_name)
{
	$sql = "DELETE FROM $table_name";
	$db->sql_query($sql);
}

//handle users/user groups differently
$sql = "DELETE FROM ".USERS_TABLE." WHERE user_id > 2";
$db->sql_query($sql);

$sql = "DELETE FROM ".USER_GROUP_TABLE." WHERE user_id > 2";
$db->sql_query($sql);

print "done";
