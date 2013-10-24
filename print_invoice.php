<?php 

/****
 *
 *  @author: jake@litwickimedia.com
 *  @SVN: $Id: print_invoice.php 7 2010-04-23 16:35:57Z jake $
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

if( isset($_GET['id']) )
{
	$invoice_id = (int) $_GET['id'];
	$invoice_row = $invoice->get_detail($invoice_id);
	$dashboard->get_project_detail($invoice_row['project_id']);
}
else
{
	redirect("$base_url/invoices.php");
}
	
//spit out the page
page_header("Invoice #$invoice_id");

$template->assign(array(
	'PRINT_VIEW'		=>	true,
	'USER_REALNAME'		=>	$user->data['user_realname'],
	'STAFF_PHONE'		=>	$staff_phone,
	'STAFF_EMAIL'		=>	$staff_email,
));

$template->display("template/dashboard_invoice_print.html");

page_footer();

?>