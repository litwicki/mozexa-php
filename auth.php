<?php 

/****
 *
 *  @author: jake@litwickimedia.com
 *  @SVN: $Id: auth.php 31 2010-05-24 03:15:38Z jake $
 *  @Copyright 2009,2010 Litwicki Media LLC
 *
 ***/
 
define('MY_DASHBOARD', true);
$root_path = './';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($root_path . 'common.' . $phpEx);

if( isset($_POST['login']) )
{
	$password 		= $_POST['password'];
	$username 		= sanitize($_POST['username']);
	$redirect_url 	= urldecode($_POST['redirect']);
	
	$expire_time 	= 0;
	
	if( $_POST['persist'] == "true" )
	{
		$expire_time = time() + 60 * 60 * 24 * $config['session_length'];
	}

	$sql = "SELECT * FROM ".USERS_TABLE." WHERE user_email='$username'";
	$result = $db->sql_query($sql);
	$row = $db->sql_fetchrow($result);

	if( phpbb_check_hash($password, $row['user_password']) && $row['status'] == 1 )
	{
		$user->session_create($row, $expire_time);
		redirect($redirect_url);
	}
	else
	{
		//update login attempts
		$user->login_attempts($username);
		
		$template->assign(array(
			'LOGIN_ERROR'	=>	true,
		));
	
		login_box($redirect_url);
	}
}
elseif( isset($_GET['logout']) )
{
	$user->session_kill();
	login_box($base_url);
}
else
{
	//are we already authorized with a session?
	$user->setup();
	$user_id = (int) $user->data['user_id'];

	if( !$user_id )
	{
		$redirect_url = isset($_GET['r']) ? urlencode($_GET['r']) : urlencode($base_url);
		login_box($redirect_url);
	}
	else
	{
		redirect($base_url);
	}
}