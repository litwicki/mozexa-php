<?php

/****
 *
 *  @author: jake@litwickimedia.com
 *  @SVN: $Id: reply.php 38 2010-06-02 02:52:38Z jake $
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

if( isset($_POST['submit']) )
{
	$parent_id 		= $_POST['message_id'];
	$message 		= sanitize($_POST['replytext']);
	$subject 		= sanitize($_POST['subject']);
	
	$message_row = array(
		'subject'		=>	$subject,
		'message'		=>	$message,
		'user_id'		=>	$user_id,
		'date_added'	=>	time(),
		'status_date'	=>	time(),
	);

	/**
	 *	A reply is just an independent message
	 *	with a link to another message (it's parent).
	 */
	 
	$message_id = $dashboard->add_message($message_row, $attachments = false, $log_new_message = false);
	$reply_id = $dashboard->add_message_reply($message_id, $parent_id);
	
	/**
	 * Build the replyrow to display on the page
	 */
	 
	$reply_row 				= $dashboard->get_message_detail($message_id, false);
	$reply_subject 			= $reply_row['subject'];
	$reply_message 			= $reply_row['message'];
	$reply_date 			= date('m/d/Y h:i A', $reply_row['date_added']);
	$reply_username 		= $reply_row['user_realname'];
	
	//the replies are based on the PARENT, not the newly created message
	$parent_row = $dashboard->get_message_detail($parent_id, false);
	$reply_count_message = $parent_row['reply_count_message'];
	
	/*$myreply = '<div class="panel bg3" id="reply'.$reply_id.'">
		<div class="inner"><span class="corners-top"><span></span></span> 
			<div class="padding">
				<h3>'.$reply_subject.'</h3>
				<p>'.$reply_message.'<span class="byline">Posted '.$reply_date.' by '.$reply_username.'</span></p>
			</div>
		<span class="corners-bottom"><span></span></span></div>
	</div>';*/
	
	$myreply = '<div class="ui-widget ui-widget-content ui-corner-all row">
		<form method="post" action="reply.php" id="killreply{$replyrow[reply].REPLY_ID}">
			<fieldset>
			  <div class="ui-widget ui-widget-content ui-corner-all ui-static pad-light" id="reply{$replyrow[reply].REPLY_ID}">
			   <div class="ui-dialog-titlebar ui-widget-header ui-corner-all ui-helper-clearfix pad">
				'.$reply_subject.'
			   </div>
			   <div class="pad">
					<p style="clear: both;">'.$reply_message.'<span class="byline">Posted '.$reply_date.' by '.$reply_username.'</span></p>
			   </div>
			  </div>
			</fieldset>
		</form>
	</div>';

	//spit out the new reply so we don't have 
	//to refresh the page we're submitting from
	
	$json_array = array(
		'replyrow'				=>	$myreply,
		//'message'				=>	$message,
		//'reply_id'				=>	$reply_id,
		//'message_id'			=>	$message_id,
		//'reply_date'			=>	date('m/d/Y h:i A', time()),
		'reply_count_message'	=>	$reply_count_message,
	);
	
	$json = json_encode($json_array);
	echo $json;
	exit;
}
elseif( isset($_POST['delete']) )
{
	$reply_id 		= $_POST['reply_id'];
	$message_id 	= $_POST['message_id'];
	$parent_id		= $_POST['parent_id'];
	$return_url		= $_POST['return_url'];
	
	$dashboard->change_status('reply', $reply_id, $closed_status);
	$dashboard->change_status('message', $message_id, $closed_status);
	
	$row = $dashboard->get_message_detail($parent_id, false);
	$reply_count 			= $row['reply_count'];
	$reply_count_message 	= $row['reply_count_message'];
	
	$json_array = array(
		'reply_count_message'	=>	$reply_count_message,
		'reply_id'				=>	$reply_id,
	);
	
	$json = json_encode($json_array);
	echo $json;

}
else
{
	exit;
}
?>
