<?php 

/****
 *
 *  @author: jake@litwickimedia.com
 *  @SVN: $Id: growl.php 17 2010-05-09 03:59:24Z jake $
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

//remove a single alert
//if( isset($_POST['remove_growl']) )
if( isset($_POST['remove_growl']) )
{
	$log_id = (int) $_POST['alert_id'];
	$sql = "UPDATE ".LOG_TABLE." SET growl=0 WHERE log_id=$log_id";
	$db->sql_query($sql);
	
	$json_array = array(
		'log_id'	=>	$log_id,
	);
	
	$json = json_encode($json_array);
	echo $json;
	
	exit;
}
else
{
	echo 'What came first, the compiler or the code?';
}

?>