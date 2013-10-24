<?php 

/****
 *
 *  @author: jake@litwickimedia.com
 *  @SVN: $Id$
 *  @Copyright 2009,2010 Litwicki Media LLC
 *
 ***/

define('MY_DASHBOARD', true);
$root_path = './';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($root_path . 'common.' . $phpEx);
include($root_path . 'includes/invoice.' . $phpEx);
include($root_path . 'includes/payment.' . $phpEx);

$invoice = new invoice();
$payment = new payment();

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

if( isset($_POST['pay']) )
{
	$invoice_id = (int) $_POST['invoice_id'];
	
	/**
	 *	If an invoice is already paid, get the hell out of here!
	 *	This can happen if a bulk invoice is paid, but monthly
	 *	rates fail to be profiled with PayPal and the customer
	 *	tries to resubmit their payment. Or, just with click-happy
	 *	users accidentally double clicking buttons and such.
	 */
	 
	$invoice_row = $invoice->get_detail($invoice_id);
	if($invoice_row['paid'])
	{
		//build a generic error
		$response = $payment->parse_error();
		exit; // <-- VERY IMPORTANT TO EXIT HERE!
	}
	
	// Month must be padded with leading zero
	$expire_month 		= str_pad($_POST['expire_month'], 2, '0', STR_PAD_LEFT);
	$expire_year		= (int) $_POST['expire_year'];
	$expiration_date 	= $expire_month . $expire_year;
	
	$payment_params = array(
		'PAYMENTACTION'		=>	'Sale',
		'AMT'				=>	str_replace('$','',$_POST['payment_amount']),
		'CREDITCARDTYPE'	=>	$_POST['card_type'],
		'ACCT'				=>	$_POST['card_number'],
		'EXPDATE'			=>	$expiration_date,
		'CVV2'				=>	sanitize($_POST['card_cvv2']),
		'FIRSTNAME'			=>	sanitize($_POST['billing_firstname']),
		'LASTNAME'			=>	sanitize($_POST['billing_lastname']),
		'STREET'			=>	sanitize($_POST['billing_street']),
		'CITY'				=>	sanitize($_POST['billing_city']),
		'STATE'				=>	sanitize($_POST['state']),
		'ZIP'				=>	sanitize($_POST['billing_zip']),
		'COUNTRYCODE'		=>	'US',
		'CURRENCYCODE'		=>	'USD',
	);
	
	$result = $payment->make_payment($payment_params);

	if($result['ACK'] == 'Success' || $result['ACK'] == 'SuccessWithWarning')
	{
		//log paypal result for the bulk payment
		$transaction_id = $payment->log_transaction($invoice_id, $result);
		
		/**
		 *	Mark the invoice as paid even though we're technically not done.
		 *	If for some reason the rate profiles cannot be created, we don't
		 *	want to accidentally re-bill for the total invoice amount if the
		 *	customer were to try and re-submit their invoice payment.
		 *
		 *	So what we do is mark the overall invoice PAID so that can't happen
		 *	and then leave it up to the Administrator to manually process
		 *	the recurring profile.
		 */
		 
		$invoice->invoice_paid($invoice_id);
		
		/**
		 *	Now cycle through the invoice and get any rates we are billing
		 *	monthly, and setup the appropriate invoice profiles.
		 */
		 
		$invoice_rates = $invoice->get_rates($invoice_id);

		foreach($invoice_rates as $rate)
		{
			$profileStartDateDay = $_POST['profileStartDateDay'];
			$padprofileStartDateDay = str_pad(date('d', $rate['start_date']), 2, '0', STR_PAD_LEFT);
			
			$profileStartDateMonth = date('m', $rate['start_date']);
			$padprofileStartDateMonth = str_pad($profileStartDateMonth, 2, '0', STR_PAD_LEFT);
			
			$profileStartDateYear = date('Y', $rate['start_date']);
			
			$profileStartDate = $profileStartDateYear . '-' . $padprofileStartDateMonth . '-' . $padprofileStartDateDay . 'T00:00:00Z'; 
			
			$billing_frequency = $rate['interval_days'];
			
			//calculate total billing cycles if there is an end-date specified
			if( $rate['end_date'] > 0 )
			{
				$second_count = abs($row['end_date'] - $row['start_date']);
				$day_count = ceil($second_count / 86400);
				$cycle_count = ceil($day_count / $interval_days);
			}
			
			$profile_params = array(
				'PAYMENTACTION'			=>	'Sale',
				'AMT'					=>	str_replace('$','',$rate['cost']),
				'CREDITCARDTYPE'		=>	$_POST['card_type'],
				'ACCT'					=>	$_POST['card_number'],
				'EXPDATE'				=>	$expiration_date,
				'CVV2'					=>	sanitize($_POST['card_cvv2']),
				'FIRSTNAME'				=>	sanitize($_POST['billing_firstname']),
				'LASTNAME'				=>	sanitize($_POST['billing_lastname']),
				'STREET'				=>	sanitize($_POST['billing_street']),
				'CITY'					=>	sanitize($_POST['billing_city']),
				'STATE'					=>	sanitize($_POST['state']),
				'ZIP'					=>	sanitize($_POST['billing_zip']),
				'COUNTRYCODE'			=>	'US',
				'CURRENCYCODE'			=>	'USD',
				'PROFILESTARTDATE'		=>	$profileStartDate,
				'DESC'					=>	$rate['profileDesc'],
				'BILLINGPERIOD'			=>	'Day',
				'BILLINGFREQUENCY'		=>	$billing_frequency,
				'TOTALBILLINGCYCLES'	=>	(int) $cycle_count,
			);
			
			$result = $payment->create_payment_profile($profile_params);
			
			if($result['ACK'] == 'Success' || $result['ACK'] == 'SuccessWithWarning')
			{
				//log the profile transaction details
				$profile_id = $payment->log_transaction_profile($invoice_id, (int) $rate['invoice_rate_id'], $result);
			}
			else
			{
				$response = $payment->parse_error($result);
			}
		}
		
		//notify client payment was successful
		$subject = 'Thank you for your payment! (#'.$invoice_id.')';
		$message = 'Thank you for your payment! You can view the details of this invoice at any time at the following URL: ' . $base_url . '/invoices.php?id=' . $invoice_id;
		$dashboard->dashboard_email($subject, $message, $client_id, false);

		$response = '<div class="ui-state-highlight ui-corner-all pad"><ul class="pretty" style="margin:0; padding:0;"><li class="approve">Your payment was processed successfully. Thank You!</li></ul></div>';
		
	}
	else
	{
		$response = $payment->parse_error($result);
	}
	
	$json_array = array(
		'response'		=>	$response,
	);
	
	$json = json_encode($json_array);
	echo $json;

	exit;
	
}
else
{
	exit;
}

?>