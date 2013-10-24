<?php 

/****
 *
 *  @author: jake@litwickimedia.com
 *  @SVN: $Id: clients.php 40 2010-06-03 05:16:22Z jake $
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

$invoice = new invoice();

if( isset($_POST['save']) || isset($_POST['saveclient']) )
{
	$client_id = (int) $_POST['client_id'];
	$timestamp = date('m/d/y h:i A', time());
	
	$phone_number	= sanitize_phone($_POST['client_phone']);
	
	$client_row = array(
		'company'	=>	sanitize($_POST['client_name']),
		'phone'		=>	$phone_number,
		'city'		=>	sanitize($_POST['client_city']),
		'zip'		=>	$_POST['client_zipcode'],
		'state'		=>	$_POST['state'],
		'address'	=>	sanitize($_POST['client_address']),
		'website'	=>	sanitize($_POST['client_website']),
	);
	
	if($client_row['company'] == '')
	{
		exit;
	}
	
	/**
	 *	Every client_id MUST have AT LEAST one user_id
	 *	in the CLIENT_USERS_TABLE associated to it.
	 *	Otherwise what's the point?
	 */
	 
	if($client_id)
	{
		$dashboard->modify_client($client_id, $client_row);
	}
	else
	{
		$client_id = $dashboard->add_client($client_row);
	}

	/**
	 *	For every selected user_id, go through and add
	 *	it if they're not already associated. Otherwise
	 *	remove it from the client association.
	 */
	if( !empty($_POST['userlist']) )
	{
		$dashboard->client_users($_POST['userlist'], $client_id);
	}
	
	$json_array = $client_row;
	$json_array['client_id'] = $client_id;

	$json = json_encode($json_array);
	echo $json;
	
	exit;
	
}
elseif( isset($_POST['email']) )
{
	$client_id 		= (int) $_POST['client_id'];
	$this_user_id 	= (int) $_POST['user_id'];
	$email_status 	= (int) $_POST['email'];
	
	$sql = "UPDATE ".CLIENT_USERS_TABLE." SET email=$email_status WHERE client_id=$client_id AND user_id=$this_user_id";
	$db->sql_query($sql);
	
	$json_array['email'] = ($_POST['email'] == 1 ? 0 : 1);
	$json = json_encode($json_array);
	echo $json;
	
	exit;
	
}
elseif( isset($_POST['delete']) )
{
	$client_id = (int) $_POST['client_id'];
	
	$sql = "UPDATE ".CLIENTS_TABLE." SET status=0 WHERE client_id=$client_id";
	$db->sql_query($sql);
	exit;
}
elseif( isset($_POST['userclients']) )
{
	/**
	 *	User submits checkboxlist from profile page
	 *	selecting/deselecting clients to associate with
	 *	the specified user_id
	 */
	 
	$this_user_id = (int) $_POST['user_id'];
	//$client_id = (int) $_POST['client_id'];
	
	$selected_clientlist = $_POST['clientlist'];

	$current_clientlist = $dashboard->mycompanies($this_user_id, $output = false);

	foreach( $current_clientlist as $client_id => $company )
	{
		if( !in_array($client_id, $selected_clientlist) )
		{
			$sql = "DELETE FROM ".CLIENT_USERS_TABLE." WHERE client_id=$client_id AND user_id=$this_user_id";
			$db->sql_query($sql);
		}
	}
	
	foreach( $selected_clientlist as $client_id )
	{
		if( !array_key_exists($client_id, $current_clientlist) )
		{
			$sql = "INSERT INTO ".CLIENT_USERS_TABLE." (client_id, user_id) VALUES ($client_id, $this_user_id)";
			$db->sql_query($sql);
		}
	}

	redirect("$base_url/users.php?id=$this_user_id");
}
elseif( isset($_POST['makeadmin']) )
{
	$client_id = (int) $_POST['client_id'];
	$this_user_id = (int) $_POST['client_user_id'];
	
	$dashboard->set_client_admin($client_id, $this_user_id);
	
	redirect("$base_url/clients.php?id=$client_id");
}
elseif( isset($_POST['adduser']) )
{
	$this_user_id = (int) $_POST['user_id'];
	$client_id = (int) $_POST['client_id'];
	
	$sql = "INSERT INTO ".CLIENT_USERS_TABLE." (client_id, user_id) VALUES ($client_id, $this_user_id)";
	$db->sql_query($sql);
	redirect("$base_url/clients.php?id=$client_id");
}
elseif( isset($_POST['deleteuser']) )
{
	$this_user_id = (int) $_POST['client_user_id'];
	$client_id = (int) $_POST['client_id'];
	
	$sql = "DELETE FROM ".CLIENT_USERS_TABLE." WHERE client_id=$client_id AND user_id=$this_user_id";
	$db->sql_query($sql);
	redirect("$base_url/clients.php?id=$client_id");
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
		$client_id = (int) $_GET['id'];
		$client_row = $dashboard->get_client_detail($client_id);

		$client_name = $client_row['company'];

		$no_invoices = "$client_name has no invoice history.";
		
		$invoice_count = $invoice->myinvoices(false,$client_id);
		$project_count = $dashboard->myprojects($user_id, $show_hidden = false, $output = true, $client_id);
		
		$no_projects = "$client_name has no active projects.";
		
		$show_detail = true;
		
		if($mode == "edit")
		{
			if( ($auth->auth_options['U_EDIT_CLIENT'] && $auth->user_group['S_STAFF']) || $auth->user_group['S_ADMINISTRATOR'] || ($client_row['super_user_id'] == $user_id) )
			{
				$page_title = "Edit Company: $client_name";
				$show_form = true;
			}
			else
			{
				$form_error = 'You do not have permission to edit company: ' . $client_name;
				$page_title = 'Permission Denied';
			}
		}
		else
		{
			$page_title = "Company Details: $client_name";
		}
		
		//allow client_users the ability to remove other users from their list of users
		$admin_user_id = $dashboard->client_admin_user($client_id);
		
	}
	else
	{
		$page_title = "View All Companies/Organizations";
		
		if($mode == "add")
		{
			if( ($auth->auth_options['U_ADD_CLIENT'] && $auth->user_group['S_STAFF']) || $auth->user_group['S_ADMINISTRATOR'] )
			{
				$page_title = "Add New Company";
				$show_form = true;
			}
			else
			{
				$form_error = 'You do not have permission to add a new client.';
			}
		}
		
		//build list of companies
		$dashboard->get_clientlist($project_id = false, $user_id = false, $output = true);
	}

	$template->assign(array(
		'S_CLIENT'			=>	true,
		'SHOW_FORM'			=>	$show_form,
		'SHOW_DETAIL'		=>	$show_detail,
		'INVOICE_COUNT'		=>	$invoice_count,
		'NO_INVOICES'		=>	$no_invoices,
		'FORM_NAME'			=>	$page_title,
		'S_CLIENT_USER'		=>	$admin_user_id == $user_id ? true : false,
		'PROJECT_COUNT'		=>	$project_count,
		'NO_PROJECTS'		=>	$no_projects,
		'CLIENT_ERROR'		=>	$form_error,
		'S_SUPER_USER'		=>	$client_row['super_user_id'] == $user_id ? true : false,
	));

	//spit out the page
	page_header($page_title);

	$template->display($root_path . "template/dashboard_clients_body.html");

	page_footer();
	
}

?>