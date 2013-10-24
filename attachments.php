<?php

/****
 *
 *  @author: jake@litwickimedia.com
 *  @SVN: $Id: attachments.php 6 2010-04-23 16:33:21Z jake $
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

$today = time();

if( isset($_POST['remove_file']) )
{
	$message_id 	= $_POST['message_id'];
	$attachment_id	= $_POST['attachment_id'];
	$sql = "UPDATE ".ATTACHMENTS_TABLE." SET status=0, status_date=$today WHERE attachment_id=$attachment_id";
	$db->sql_query($sql);
}
elseif( isset($_POST['add_file']) )
{
	$message_id 	= $_POST['message_id'];
	$attachment_id	= $_POST['attachment_id'];
	$sql = "UPDATE ".ATTACHMENTS_TABLE." SET status=1, status_date=$today WHERE attachment_id=$attachment_id";
	$db->sql_query($sql);
}
else
{
	//get out of here!
	exit;
}

?>