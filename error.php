<?php

/****
 *
 *  @author: jake@litwickimedia.com
 *  @SVN: $Id: error.php 7 2010-04-23 16:35:57Z jake $
 *  @Copyright 2009,2010 Litwicki Media LLC
 *
 ***/

define('IN_PHPBB', true);
$phpbb_root_path = './';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.' . $phpEx);

$user->session_begin();
$auth->acl($user->data);
$user->setup();

$user_id = $user->data['user_id'];
$username = $user->data['user_realname'];

$error_num = (int) $_GET['error'];
if($error_num)
{
	$error_page = 'template/' . $error_num . ".html";
}
else
{
	//wtf?
	exit;
}

page_header('Websites with higher standards!');

//output the page
$template->display($root_path . $error_page);

page_footer();

?>