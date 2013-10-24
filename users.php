<?php 

/****
 *
 *  @author: jake@litwickimedia.com
 *  @SVN: $Id: users.php 38 2010-06-02 02:52:38Z jake $
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

$company_name = $config['company_name'];

if( !$user_id )
{
	login_box("$base_url");
}

//setup user permissions
$auth->setup($user_id);

//build user dashboard
$dashboard = new dashboard();
$dashboard->setup($user_id);

if( isset($_POST['save']) || isset($_POST['saveuser']) )
{
	$this_user_id = (int) $_POST['user_id'];
	$timestamp = date('m/d/y h:i A', time());
	
	$user_email = sanitize($_POST['user_email']);
	
	$user_firstname = sanitize(ucwords($_POST['user_firstname']));
	$user_lastname = sanitize(ucwords($_POST['user_lastname']));
	$user_realname = $user_firstname . ' ' . $user_lastname;

	$user_phone = sanitize_phone($_POST['user_phone']);
	
	$user_aim = sanitize($_POST['user_aim']);
	$user_sms = isset($_POST['user_sms']) ? 1 : 0;

	//is the email unique?
	//someone with the same as an existing user
	$sql = "SELECT user_id FROM ".USERS_TABLE." WHERE user_email='" . $user_email . "'";
	
	if($this_user_id)
	{
		$sql .= " AND user_id <> $this_user_id";
	}

	$result = $db->sql_query($sql);
	$user_count = $db->sql_affectedrows($result);
	
	if($user_count > 0)
	{
		$user_error = 'That email address is already being used by another user!';
		$bad_email = true;
	}
	else
	{
		if($this_user_id)
		{
			$update_user_row = array(
				'user_email'		=>	$user_email,
				'user_realname'		=>	$user_realname,
				'user_phone'		=>	$user_phone,
				'user_firstname'	=>	$user_firstname,
				'user_lastname'		=>	$user_lastname,
				'user_sms'			=>	$user_sms,
				'user_aim'			=>	$user_aim,
			);
		
			$log_message = "Your profile has been updated.";
		
			/**
			 *	If a new password is specified, and confirmed then the user obviously wants to reset the
			 *	password -- so assign the new password to the user array for modify_user() to handle
			 */
			
			if( ($_POST['new_password'] == $_POST['new_password_confirm']) && strlen($_POST['new_password']) > 6 )
			{
				$new_password 				= $_POST['new_password'];
				$update_user_row['user_password'] 	= phpbb_hash($new_password);
				$password = $new_password; //for emailing later
			}

			//user exists, so the form we're using is dashboard_user_form.html
			$user_row = $user->modify($this_user_id, $update_user_row);
			$email_message = "$user_firstname,\nYour dashboard profile has been updated! Your updated profile data is below:\n\n";
		}
		else
		{		
			$log_message = "Welcome to your $company_name dashboard!";
			$email_message = "Welcome to $company_name!\n\nYour dashboard account details are below.\n\n";

			$password = substr(md5($user_email), 0, 8);

			$user_row = array(
				'user_phone'		=>	$user_phone,
				'user_email'		=>	$user_email,
				'user_gender'		=>	$_POST['user_gender'],
				'user_password'     =>	phpbb_hash($password),
				'user_ip'           =>	$_SERVER['REMOTE_ADDR'],
				'user_regdate'      =>	time(),
				'user_realname'		=>	$user_realname,
				'user_lastname'		=>	$user_lastname,
				'user_firstname'	=>	$user_firstname,
				'user_aim'			=>	$user_aim,
			);

			$this_user_id  = $user->add_new($user_row, $group_row);
		}

		/**
		*	GROUPS MANAGEMENT
		*	-----------------------------
		*	Most users cannot modify their own groups, so we don't allow them
		*	to even see the group display, but we also don't want their groups
		*	to be reset accidentally.
		*/
		if( isset($_POST['user_groups']) )
		{
			//save user groups
			$user->group_assign($this_user_id, $_POST['user_groups']);
		}
		
		/**
		*	*PERMISSIONS MANAGEMENT*
		*	-----------------------------
		*	ALL Permission values begin with u_ and by design no other form fields in the user_form should begin with u_
		*	so we know that permission values are exclusively prefixed with u_
		*
		*	Similar to the groups, we don't allow most users to process their own permissions, so we don't display the
		*	form inputs to them. In this case, we don't want to process all blank permissions and remove what they do
		*	already have. So we flag permissions when we're allowing a user to process them, and otherwise, ignore them.
		*/
		
		if( isset($_POST['process_permissions']) )
		{
			foreach($_POST as $key => $value)
			{
				if( preg_match("/^u_.*$/",$key) )
				{
					$selected_permissions[$key] = $value;
				}
			}
			
			foreach($selected_permissions as $auth_option => $auth_option_id)
			{
				if( preg_match("/^u_.*/",$auth_option) )
				{
					//if the user does not currently have this permission, add it
					if( !array_key_exists($auth_option_id, $auth->auth_options) )
					{
						$auth->add_permission($this_user_id, $auth_option_id, $auth_setting = 1);
					}
				}
			}

			foreach($auth->auth_options as $auth_option => $auth_option_id)
			{
				//if the permission is NOT selected, but is currently assigned, remove it
				if( !in_array($auth_option_id, $selected_permissions) )
				{
					$auth->remove_permission($this_user_id, $auth_option_id);
				}
			}
		}
		
		$user_data = $user->detail($this_user_id);
		
		foreach($user_data as $field => $value)
		{
			if($field == 'user_sms')
			{
				$field = "SMS Notices";
				if( $value == "0" )
				{
					$value = "No";
				}
				else
				{
					$value = "Yes";
				}
			}
			
			if($field == 'user_phone')
			{
				$value = parse_phone($value);
			}
			
			$field = ucfirst(str_replace("user_","",$field));
			$email_message .= "$field: $value\n";
		}
		
		$email_message .= $new_password == '' ? "\n" : "Password: $password\n\n";

		$user_groups = $user->mygroups($this_user_id);

		if(count($user_groups))
		{		
			$email_message .= "You belong to the following dashboard groups: ";
			
			foreach($user_groups as $group_id => $group_name)
			{
				$group_list .= "$group_name, ";
			}
			
			$group_list = preg_replace("/(.*),/","$1",$group_list);
			
			$email_message .= "$group_list\n\n";
			
		}

		$email_message .= "To access the dashboard, login with your email address and password here: $base_url/auth.php?mode=login\n\n";

		if(!$bad_email)
		{	
			if( isset($_POST['user_groups']) )
			{
				//Manage User Group(s)
				$user->group_assign($this_user_id, $_POST['user_groups']);
			}
			
			//email the user with their account info
			$email_subject = $config['company_name'] . " - Dashboard Account";
			email_user($email_subject, $email_message, $this_user_id, $html_email = false, $priority = 3, $attachments = false);

			/* Log a welcome message for this user's growl */
			$profile_link = '<a href="/users.php?id='.$this_user_id.'">'.$log_message.'</a>';
			$dashboard->dashboard_log($this_user_id, $this_user_id, time(), $profile_link);
		}
	}
	
	$user_row = $dashboard->user_details($this_user_id);

	$user_row['user_error'] = $bad_email ? $user_error : '';
	$user_row['bad_email'] = $_POST['user_email'];
	
	$json_array = $user_row;
		
	$json = json_encode($json_array);
	echo $json;
	
	exit;
	
}
elseif( isset($_POST['delete']) )
{
	$this_user_id = (int) $_POST['user_id'];
	
	$sql = "UPDATE ".USERS_TABLE." SET status=0 WHERE user_id=$this_user_id";
	$db->sql_query($sql);
	
	exit;
}
elseif( isset($_POST['savepermissions']) )
{
	$this_user_id = (int) $_POST['user_id'];
	$selected_permissions = $_POST;

	//first get all user permissions so we can remove ones
	//that were de-selected by the admin

	foreach($selected_permissions as $auth_option => $auth_option_id)
	{
		if( preg_match("/^u_.*/",$auth_option) )
		{
			//if the user does not currently have this permission, add it
			if( !array_key_exists($auth_option_id, $auth->auth_options) )
			{
				$auth_row = array(
					'auth_option_id'	=>	$auth_option_id,
					'user_id'			=>	$this_user_id,
					'auth_setting'		=>	$auth_setting,
				);
				
				$auth->add_permission($auth_row);
			}
		}
	}
	
	foreach($auth->auth_options as $auth_option => $auth_option_id)
	{
		//if the permission is NOT selected, but is currently assigned, remove it
		if( !in_array($auth_option_id, $selected_permissions) )
		{
			$auth->remove_permission($this_user_id, $auth_option_id);
		}
	}

	exit;
}
elseif( isset($_POST['saveavatar']) )
{
	$this_user_id = (int) $_POST['user_id'];
	
	$old_image = $_FILES['avatar']['tmp_name'];

	$new_image = 'avatar-' . $this_user_id . '.jpg';
	
	$new_image_path = FILE_PATH . '/avatars/' . $new_image;
	
	if( move_uploaded_file($old_image, $new_image_path) )
	{
		$cmd = 'convert -resize 100x100 ' . $new_image_path  . ' ' . $new_image_path;
		@exec($cmd);
	
		$sql = "UPDATE ".USERS_TABLE." SET user_avatar='$new_image' WHERE user_id=$this_user_id";
		$db->sql_query($sql);
	}
	
	redirect("$base_url/users.php?id=$this_user_id");
	
}
else
{
	//Are we trying to add, edit, or view a user?
	if( isset($_GET['mode']) )
	{
		if( $_GET['mode'] == "add" || $_GET['mode'] == "edit" )
		{
			$mode = $_GET['mode'];
		}
	}
	
	if( isset($_GET['id']) && is_numeric($_GET['id']) )
	{
		//viewing a specific users profile
		$this_user_id = (int) $_GET['id'];
		$show_detail = true;
		
		$template->assign(array(
			'S_EDIT_PROFILE'		=>	true,
		));

		/**
		 *	Display a limited number of fields for
		 *	the user to review, and modify, if they
		 *	are viewing their own profile.
		 */
		 
		$user_row = $dashboard->user_details($this_user_id);

		if(empty($user_row))
		{
			$template->assign(array(
				'INVALID_USER'	=>	true,
			));
		}

		//array[group_id] = group_name
		$user_groups = $user->mygroups($this_user_id);
		
		//get user groups/roles
		$user->grouplist($this_user_id);

		foreach( $user_groups as $group_id => $group_name )
		{
			if( $group_name == 'ADMINISTRATOR' )
			{
				$is_admin = true;
			}
			
			if( $group_name == 'MANAGER' )
			{
				$is_manager = true;
			}
			
			if( $group_name == 'CONTRACTOR' )
			{
				$is_contractor = true;
			}
			
			if( $group_name == 'STAFF' )
			{
				$is_staff = true;
			}
		}
		
		$user_gender = $user_row['user_gender'];
		
		//default a blank avatar
		$user_avatar = ($user_gender == 'f' ? 'female.jpg' : 'male.jpg');
		
		//overwrite with the real avatar if there is one
		if( $user_row['user_avatar'] != '' && file_exists(FILE_PATH . '/avatars/' . $user_row['user_avatar']) )
		{
			$user_avatar = $user_row['user_avatar'];
		}
		
		$template->assign(array(
			'PROFILE_USER_REALNAME'		=>	$user_row['user_realname'],
			'PROFILE_USER_PHONE'		=>	parse_phone($user_row['user_phone']),
			'PROFILE_USER_LASTNAME'		=>	$user_row['user_lastname'],
			'PROFILE_USER_FIRSTNAME'	=>	$user_row['user_firstname'],
			'PROFILE_USER_EMAIL'		=>	$user_row['user_email'],
			'PROFILE_USER_ID'			=>	$user_row['user_id'],
			'PROFILE_IS_STAFF'			=>	$is_staff,
			'PROFILE_IS_MANAGER'		=>	$is_manager,
			'PROFILE_IS_CONTRACTOR'		=>	$is_contractor,
			'PROFILE_IS_ADMINISTRATOR'	=>	$is_admin,
			'PROFILE_USER_SMS'			=>	$user_row['user_sms'],
			'PROFILE_USER_AIM'			=>	$user_row['user_aim'],
			'PROFILE_USER_GENDER'		=>	$user_gender,
			'PROFILE_USER_AVATAR'		=>	$user_avatar,
		));
		
		$work_types[] = array_change_key_case($row, CASE_UPPER);
		
		$page_title = $user_row['user_realname'];
		
		//display permissions
		$auth->mypermissions($this_user_id);
	
		//for staff members without edit permissions
		//display what permissions they DO have for their
		//viewing only
		
		$sql = "SELECT * 
				FROM 
					".AUTH_OPTIONS_TABLE." o 
					JOIN ".AUTH_USERS_TABLE." u ON u.auth_option_id=o.auth_option_id 
				WHERE 
					u.user_id=$this_user_id 
				ORDER BY 
					auth_option";
					
		$result = $db->sql_query($sql);
		while( $row = $db->sql_fetchrow($result) )
		{
			$auth_row = array(
				'AUTH_ICON'				=>	$row['ico'],
				'AUTH_OPTION'			=>	$row['auth_option'],
				'AUTH_OPTION_CLEAN'		=>	ucwords(str_replace("_"," ",str_replace("u_","",$row['auth_option']))),
			);
			
			$user_auths[] = $auth_row;
		}
		
		$template->assign('user_permissions',$user_auths);

		/**
		 *	Now that the user information is processed
		 *	get a complete list of all clients this
		 *	user_id is associated with.
		 */
		 
		$my_companies = $dashboard->mycompanies($this_user_id, true);

		/**
		 *	Now dislay a full list of all users for a quick dropdown for staff/managers
		 */

		if( $auth->user_group['S_STAFF'] || $auth->user_group['S_MANAGER'] )
		{
			$dashboard->get_userlist();
		}
	}
	else
	{
		if( $mode == "add" )
		{
			$show_form = true;
			$page_title = "Add New User";
			
			$template->assign(array(
				'S_NEW_PROFILE'		=>	true,
			));
			
			$auth->mypermissions();
			
			//build group list
			$user->grouplist();
		}
		else
		{
			if( $auth->user_group['S_STAFF'] )
			{
				$user_count = (int) $dashboard->myusers();
				$page_title = "Users ($user_count)";
			}
			else
			{
				//not staff, redirect to their own profile
				redirect("$base_url/users.php?id=$user_id");
			}
		}
	}

}

//spit out the page
page_header($page_title);

$template->assign(array(
	'ALLOW_EDITS'			=>	$auth->user_group['S_ADMINISTRATOR'] || $this_user_id == $user_id || $auth->options['U_EDIT_USER'] ? true : false,
	'S_USER'				=>	true,
	'S_STAFF'				=>	is_staff($user_id) ? true : false,
	'S_CLIENT'				=>	is_client($user_id) ? true : false,
	'SHOW_DETAIL'			=>	$show_detail,
	'USER_COUNT'			=>	$user_count,
	'SHOW_DETAIL'			=>	$show_detail,
	'SHOW_USERLIST'			=>	$user_count > 0 ? true : false,
	'SHOW_FORM'				=>	$show_form,
	'SHOW_MESSAGE_FORM'		=>	$my_project_count > 0 ? true : false,
	'FORM_ERROR'			=>	$form_error,
	'THIS_USER_ID'			=>	$this_user_id,
	'FORM_NAME'				=>	$page_title,
	'NO_USERS'				=>	'No users found!',
	'S_MY_DASHBOARD'		=>	true,
	'S_PROFILE_DETAILS'		=>	isset($_GET['id']) ? true : false,
	'S_PROFILE'				=>	true,
));

$template->display('template/dashboard_user_body.html');

page_footer();

?>