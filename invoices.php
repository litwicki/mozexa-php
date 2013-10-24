<?php 

/****
 *
 *  @author: jake@litwickimedia.com
 *  @SVN: $Id: invoices.php 47 2010-06-07 03:10:36Z jake $
 *  @Copyright 2009,2010 Litwicki Media LLC
 *
 ***/

define('MY_DASHBOARD', true);
$root_path = './';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($root_path . 'common.' . $phpEx);
include($root_path . 'includes/invoice.' . $phpEx);

$invoice = new invoice();

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

/**
 *	STATUS TABLE
 *	---------------
 *	0 ->	deleted
 *	1 ->	sent to customer, pending payment
 *	2 ->	paid
 *	3 ->	work in progress
 */

if( isset($_POST['save']) )
{
	$invoice_id = (int) $_POST['invoice_id'];
	
	//first, build the invoice
	$client_id 		= (int) $_POST['client_id'];
	$comments 		= sanitize($_POST['comments']);
	
	//invoice elements
	$invoice_rates = $_POST['invoice_rates'];
	$invoice_hours = $_POST['invoice_hours'];

	$invoice_row = array(
		'client_id'				=>	$client_id,
		'comments'				=>	$comments,
		'status_date'			=>	time(),
		'user_id'				=>	$user_id,
	);
	
	//do not allow edit on an invoice by design
	$invoice_row['date_added'] 			= time();
	$invoice_row['invoice_date'] 		= strtotime($_POST['invoice_date']);
	$invoice_row['invoice_date_due'] 	= strtotime($_POST['invoice_date_due']);
	
	//add the invoice
	$invoice_id = $invoice->add($invoice_row);

	//parse all invoice items into one array for later
	for( $i=0; $i<count($_POST['item_name']); $i++ )
	{
		$invoice_items[$i]['item_name'] = sanitize($_POST['item_name'][$i]);
		$invoice_items[$i]['item_description'] = sanitize($_POST['item_description'][$i]);
		$invoice_items[$i]['item_price'] = $_POST['item_price'][$i];
		$invoice_items[$i]['invoice_id'] = $invoice_id;
	}
	
	//do the same for discounts
	//parse all invoice items into one array for later
	for( $i=0; $i<count($_POST['discount_name']); $i++ )
	{
		$invoice_discounts[$i]['discount_name'] = sanitize($_POST['discount_name'][$i]);
		$invoice_discounts[$i]['discount_reason'] = sanitize($_POST['discount_reason'][$i]);
		$invoice_discounts[$i]['discount_amount'] = $_POST['discount_amount'][$i];
		$invoice_discounts[$i]['invoice_id'] = $invoice_id;
	}
	
	/**
	 *	Now that we have the invoice_id, do the rest
	 *	1. Add billable hours
	 *	2. Add selected rates
	 *	3. Add "one-off" invoice items
	 *	4. Add invoice discount(s)
	 */
	
	//	1) add the task hours billed on this invoice
	if( !empty($invoice_hours) )
	{
		$invoice->add_hours($invoice_id, $client_id, $invoice_hours);
	}
	
	//	2) add services to this invoice
	if( !empty($invoice_rates) )
	{
		$invoice->add_rates($invoice_id, $client_id, $invoice_rates);
	}
	
	//	3) add invoice items
	if( !empty($invoice_items) )
	{
		$invoice->add_items($invoice_id, $invoice_items);
	}
	
	//	4) add discounts
	if( !empty($invoice_discounts) )
	{
		$invoice->add_discounts($invoice_id, $invoice_discounts);
	}
	
	//redirect("$base_url/invoices.php?mode=recurrence&client_id=$client_id");
	$invoice_row['invoice_id'] = $invoice_id;
	$json = json_encode($invoice_row);
	echo $json;
	exit;
	
}
elseif( isset($_POST['publish']) )
{
	$invoice_id = (int) $_POST['invoice_id'];
	$invoice->publish($invoice_id);
	
	//email the client
	$client_id = (int) $_POST['client_id'];
	$client_row = $dashboard->get_client_detail($client_id);
	$company_name = $client_row['company'];

	$invoice_link = $base_url . "/print/invoice-$invoice_id";

	$subject = $config['company_name'] . " - Invoice #$invoice_id";
	$message = "An invoice has just been generated for $company_name: $invoice_link\n\n";
	
	$dashboard->dashboard_email($subject, $message, $client_id, $html_email = false, $priority = 5, $attachments = false);
	
	redirect("$base_url/invoices.php");

}
elseif( isset($_POST['delete']) )
{
	$invoice_id = (int) $_POST['invoice_id'];
	
	/**
	 *	Here we physically delete the invoice and all relationships to
	 *	the invoice because we don't want to store any history to
	 *	possibily contaminate revenue data, or have to create extra
	 *	status flags for rates and hours that are linked to a "deleted"
	 *	invoice.
	 */
	
	$invoice->delete($invoice_id);

	redirect("$base_url/invoices.php");
}
elseif( isset($_POST['saverecurrences']) )
{
	$invoice_rates	= $_POST['invoice_rate_ids'];
	$start_dates	= $_POST['start_dates'];
	$end_dates		= $_POST['end_dates'];
	$intervals		= $_POST['intervals'];
	
	/**
	 *	We've already done jQuery validation, but
	 *	because we're dealing with payment/revenue stuff here,
	 *	do some additional server-side validation overlap to be 100% safe.
	 */
	 
	$item_count = (int) count($invoice_rates);
	
	$response = '<ul>';
	
	for( $i=0; $i<$item_count; $i++ )
	{
		$start_date 		= (int) strtotime($start_dates[$i]);
		$end_date 			= (int) strtotime($end_dates[$i]);
		$invoice_rate_id 	= (int) $invoice_rates[$i];
		$interval 			= (int) $intervals[$i];
		
		if($end_date > 0 && ($end_date >= $start_date))
		{
			$response.= '<li><span class="alert ico">&nbsp;</span>End date error with invoice-rate #'.$invoice_rate_id.'</li>';
		}
		elseif($interval < 0 )
		{
			$response.= '<li><span class="alert ico">&nbsp;</span>Invalid billing cycle interval for invoice-rate #'.$invoice_rate_id.'</li>';
		}
		else
		{
			$recurrence = array(
				'invoice_rate_id'	=>	$invoice_rate_id,
				'interval_days'		=>	$interval,
				'start_date'		=>	$start_date,
				'end_date'			=>	$end_date,
			);
			
			$invoice->add_recurrence($recurrence);
			$response .= '<li><span class="approve ico">&nbsp;</span>Added recurring bill for invoice-rate #'.$invoice_rate_id.'</li>';
		}
	}
	
	$response .= '</ul>';

	$json_response = array(
		'response'	=>	$response,
	);
	
	$response = json_encode($json_response);
	echo $response;
	exit;
	
}
else
{
	//default page filename, we may reassign later depending on mode
	$page_filename = "template/dashboard_invoice_body.html";
	
	//what are we doing?
	if( isset($_GET['mode']) )
	{
		if( $_GET['mode'] == "add" || $_GET['mode'] == "print" || $_GET['mode'] == 'recurrence' || $_GET['mode'] == 'pay' )
		{
			$mode = $_GET['mode'];
		}
	}

	$add_recurrence = false;
	
	if( $mode == "add" )
	{
		if( ($auth->options['U_ADD_INVOICE'] && $auth->user_group['S_STAFF']) || $auth->user_group['S_ADMINISTRATOR'] )
		{
			$show_form = true;
			$page_title = "Create Invoice";
			
			/**
			 *	User selected a client to create an invoice for. We need to get all
			 *	projects this client is the owner of, and build their unpaid hours.
			 */
			if( isset($_GET['c']) )
			{
				//get all the hours for this project
				$client_id = (int) $_GET['c'];
				$client_row = $dashboard->get_client_detail($client_id);
				
				$company_name = $client_row['company'];
				$page_title = "Create Invoice for $company_name";

				$billable_hours = $invoice->unpaid_hours($client_id);

				$company_name = $client_row['company'];
				$no_unpaid_hours = $company_name . ' has no billable hours to be invoiced!';
				
				$template->assign(array(
					'CLIENT_ID'	=>	$client_id,
				));
				
			}
			
			//build list of companies
			//$dashboard->get_clientlist($project_id = false, $user_id = false, $output = true);
			$dashboard->get_clientlist(false, false, true);
			
			$form_name = $page_title;
		}
		else
		{
			$form_error = 'You are not authorized to add a new invoice!';
			$page_title = "Invoices";
		}
		
	}
	elseif( $mode == 'recurrence' )
	{
		$add_recurrence = true;
		$show_detail = true;
		$show_form = true;
		$page_title = $form_name = "New Invoice Profile";
		
		if( isset($_GET['client_id']) )
		{
			$client_id = (int) $_GET['client_id'];
			$show_rates = true;

			$client_row = $dashboard->get_client_detail($client_id);

			$selected_client = sanitize($_GET['select_client_id']);

			//get basic invoice details
			//$invoice->get_detail($invoice_id);
			$invoice_count = $invoice->myinvoices(false, $client_id);
			
			$form_name = $client_row['company'] . ' - ' . $invoice_count . ' Invoice(s)';
			
			//with this client_id, get all the rates eligible for recurring billing
			$sql = "SELECT 
						ir.invoice_id, ir.rate_id, ir.invoice_rate_id,
						r.name, r.description, r.cost,
						i.date_added, i.invoice_date_due, i.invoice_date,
						x.start_date, x.end_date, x.interval_days 
					FROM 
						".INVOICE_RATES_TABLE." ir 
						JOIN ".RATES_TABLE." r ON r.rate_id = ir.rate_id 
						JOIN ".INVOICES_TABLE." i ON i.invoice_id=ir.invoice_id 
						LEFT JOIN ".INVOICE_RECURRENCE_TABLE." x ON x.invoice_rate_id=ir.invoice_rate_id 
					WHERE 
						i.client_id=$client_id";

			$result = $db->sql_query($sql);
			
			while( $row = $db->sql_fetchrow($result) )
			{
				$invoice_rate_id = $row['invoice_rate_id'];
				
				$_sql = "SELECT invoice_rate_id FROM ".INVOICE_RECURRENCE_TABLE;
				$_result = $db->sql_query($_sql);
				while( $_row = $db->sql_fetchrow($_result) )
				{
					if($_row['invoice_rate_id'] == $invoice_rate_id)
					{
						$recurrence_flag = true;
					}
				}

				$row['RECURRENCE_FLAG'] = $recurrence_flag;
				
				if($recurrence_flag)
				{
					$row['start_date'] = date($config['date_short'], $row['start_date']);
					$row['end_date'] = $row['end_date'] == 0 ? 'Indefinite' : date($config['date_short'], $row['end_date']);
				}
				
				$row['INVOICE_UNIQUE_ID'] = $row['date_added'] . '-' . $row['invoice_id'];
				$row['INVOICE_DATE_DUE'] = date($config['date_short'], $row['invoice_date_due']);

				$raterow[] = array_change_key_case($row, CASE_UPPER);
				
				$recurrence_flag = false;
			}
			
			$template->assign('raterow',$raterow);
		}
	}
	elseif( $mode == 'print' )
	{
		$page_filename = "template/dashboard_invoice_print.html";
	}
	else
	{
		/**
		 *	We're not trying to add an invoice,
		 *	but if an ?id= is specified, we must be
		 *	trying to view the details of one.
		 */
		if( isset($_GET['id']) )
		{
			$invoice_id = (int) $_GET['id'];
			$page_title = "View Invoice #$invoice_id";
			

			//display invoice detail
			$invoice_row = $invoice->get_detail($invoice_id);

			if(!empty($invoice_row))
			{
				$show_detail = true;
				
				if( $mode == 'pay' )
				{
					$page_filename = "template/dashboard_invoice_payment.html";
				}
			}
			else
			{
				$invoice_count = $invoice->myinvoices();
				
				$page_title = "View All Invoices";
				$form_name = $page_title;
				
				$template->assign(array(
					'INVOICE_ERROR'		=>	'Invalid Invoice: #'.$invoice_id,
				));
			}
		}
		else
		{
			/**
			 *	We're not adding, editing, or printing, so just display
			 *	a full list of all invoices -- whew.
			 */
			 
			$page_title = $auth->user_group['S_STAFF'] ? "View Company Invoices" : "View My Invoices";
			
			$view = $_GET['view'];
			if( $view == "paid" )
			{
				$invoice_count = $invoice->myinvoices(1);
				$page_title = "My Paid Invoices";
				$no_invoices = 'There are no paid invoices';
			}
			elseif( $view == "pending" )
			{
				$invoice_count = $invoice->myinvoices(0);
				$page_title = "My Pending Invoices";
				$no_invoices = 'There are no unpaid invoices';
			}
			elseif( $view == "pastdue" )
			{
				$invoice_count = $invoice->myinvoices(false, false, true);
				$no_invoices = 'There are no past due invoices';
			}
			else
			{
				$invoice_count = $invoice->myinvoices();
			}

			$form_name = $page_title;
			
		}
	}
	
	$no_invoices = ($no_invoices == '' ? 'No invoices found!' : $no_invoices);
	
}

//spit out the page
page_header($page_title);

$template->assign(array(
	'S_INVOICE'				=>	true,
	'SHOW_DETAIL'			=>	$show_detail,
	'SHOW_FORM'				=>	$show_form,
	'NO_UNPAID_HOURS'		=>	$no_unpaid_hours,
	'S_DASHBOARD_PAGE'		=>	true,
	'STAFF_PHONE'			=>	$config['staff_phone'],
	'STAFF_EMAIL'			=>	$config['staff_email'],
	'INVOICE_COUNT'			=>	$invoice_count,
	'NO_INVOICES'			=>	$no_invoices,
	'FORM_NAME'				=>	$form_name,
	'INVOICE_ERROR'			=>	$form_error,
	'SHOW_BILLABLE_HOURS'	=>	$billable_hours > 0 ? true : false,
	'ADD_RECURRENCE'		=>	$add_recurrence,
	'SHOW_RATES'			=>	$show_rates,
	'SELECTED_CLIENT'		=>	$selected_client,
	'S_PRINT'				=>	$mode == 'print' ? true : false,
	'INVOICE_ID'			=>	$invoice_id,
	'S_PAYMENT'				=>	$mode == 'pay' ? true : false,
	'PAYMENT_AMOUNT'		=>	$invoice_row['TOTAL'],
	'SHOW_INVOICE_DETAILS'	=>	$invoice_count > 0 ? true : false,
));

$template->display($root_path . $page_filename);

page_footer();

?>