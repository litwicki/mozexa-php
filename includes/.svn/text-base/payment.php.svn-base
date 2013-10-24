<?php
/**
 *  @author: jake@litwickimedia.com
 *  @SVN: $Id$
 *  @Copyright 2009,2010 Litwicki Media LLC
 *	-----------------------------------------------------------------------
 *	Payment class interacts with PayPal (Website Payments Pro) merchant
 *	account to handle direct payments, recurring payments, and express
 *	checkout payments seamlessly on the dashboard website to allow for
 *	instant payment processing online or via phone through administrators.
 *	-----------------------------------------------------------------------
 *	### This class requires PHP5 with CURL enabled! ###
 *
 */ 
 
if (!defined('MY_DASHBOARD'))
{
	exit;
}

class payment
{
	
	var $response 			= array();
	var $request			= array();
	var $success 			= '';
	var $error_message 		= '';
	var $error_num 			= '';
	
	function __construct()
	{
		if( !in_array('curl', get_loaded_extensions()) )
		{
			$this->__destruct();
		}
	}
	
	function __destruct()
	{
		unset($this);
		return true;
	}

	/**
	 *	@purpose:	Take a query_string and send it to paypal
	 *	@method:	type of transaction for paypal to process
	 *	@nvp_url:	query_string of parameters to send to paypal
	 */
	function api_call($method, $nvp_url)
	{
		global $config;
		
		//setting the curl parameters.
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $config['paypal_endpoint']);
		curl_setopt($ch, CURLOPT_VERBOSE, 1);

		//turning off the server and peer verification(TrustManager Concept).
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch, CURLOPT_POST, 1);

		$nvp_request = 'METHOD=' . urlencode($method)
		. "&VERSION=" . urlencode('62.0')
		. $nvp_url;

		//setting the nvp_request as POST FIELD to curl
		curl_setopt($ch, CURLOPT_POSTFIELDS, $nvp_request);

		$nvp_response = $this->parse_nvp(curl_exec($ch));
		$nvp_request_array = $this->parse_nvp($nvp_request);

		if( curl_errno($ch) ) 
		{
			$error_message = curl_error($ch);
			$error_num = curl_errno($ch);
		} 
		else 
		{
			//closing the curl
			curl_close($ch);
		}

		return $nvp_response;
		
	}

	/** 
	 *	This function will take NVPString and convert it to an Associative Array and it will decode the response.
	 *	It is usefull to search for a particular key and displaying arrays.
	 *	@nvp_str is NVPString.
	 *	@nvp_array is Associative Array.
	 */

	function parse_nvp($nvp_str)
	{
		$start = 0;
		$nvp_array = array();
		
		while( strlen($nvp_str) )
		{
			//postion of Key
			$keypos = strpos($nvp_str, '=');
			
			//position of value
			$valuepos = strpos($nvp_str, '&') ? strpos($nvp_str, '&') : strlen($nvp_str);

			//getting the Key and Value values and storing in a Associative Array
			$keyval = substr($nvp_str, $start, $keypos);
			$valval = substr($nvp_str, $keypos + 1, $valuepos - $keypos - 1);
			
			//decoding the respose
			$nvp_array[urldecode($keyval)] = urldecode($valval);
			$nvp_str = substr($nvp_str, $valuepos + 1, strlen($nvp_str));
		}
		
		return $nvp_array;
	}
	
	/**
	 *	@purpose:		Convert array of parameters into nvp_string to use in api_call()
	 *	@nvp_array:		Associative array of parameters
	 */
	function nvp_string($nvp_array, $header = true)
	{
		foreach($nvp_array as $key => $val)
		{
			$key = strtoupper($key);
			$val = urlencode($val);
			$nvp_string .= "&$key=$val";
		}
		
		if($header)
		{
			$nvp_string = $this->nvp_header() . $nvp_string;
		}
		
		return $nvp_string;
		
	}
	
	/**
	 *	The string 'nvp_header' contains the sensitive username, password, and api signature
	 *	for using with paypal. For this reason, we must encrypt/decrypt the data
	 *	for security purposes.
	 */
	function nvp_header()
	{
		global $config;

		$paypal_username 	= $config['paypal_username'];
		$paypal_password 	= $config['paypal_password'];
		$paypal_signature 	= $config['paypal_signature'];
		
		$nvp_header = "&PWD=" . urlencode($paypal_password) . "&USER=" . urlencode($paypal_username) . "&SIGNATURE=" . urlencode($paypal_signature);
		
		return $nvp_header;
	}

	/**
	 *	@purpose: 	Take array of associative payment details, convert to string, and send to paypal to make a direct payment.
	 *	@nvp_array:	Associative array of values to pass to api_call as a string
	 *		ARRAY PARAMETERS:
	 *		@PAYMENTACTION
	 *		@AMT
	 *		@CREDITCARDTYPE
	 *		@ACCT
	 *		@EXPDATE
	 *		@CVV2
	 *		@FIRSTNAME
	 *		@LASTNAME
	 *		@STREET
	 *		@CITY
	 *		@STATE
	 *		@ZIP
	 *		@COUNTRYCODE
	 *		@CURRENCYCODE
	 */
	 
	function make_payment($nvp_array)
	{
		$nvp_string = $this->nvp_string($nvp_array);
		return $this->api_call("doDirectPayment", $nvp_string);
	}
	
	/**
	 *	VOID a transaction
	 *	@param $nvp_array: associative array of parameters
	 *		ARRAY PARAMETERS:
	 *		@AUTHORIZATIONID
	 *		@NOTE
	 */
	 
	function void_payment($nvp_array)
	{
		$nvp_string = $this->nvp_string($nvp_array);
		$this->api_call('DOVoid', $nvp_string);
	}
	
	/**
	 *	REFUND a transaction
	 *	With a given transaction id (originally from paypal), refund transaction
	 *	@param: $nvp_array: associative array of parameters
	 *		PARAMS:
	 *		@TRANSACTIONID
	 *		@REFUNDTYPE=FULL|PARTIAL
	 *		@CURRENCYCODE=USD
	 *		@NOTE
	 *		@AMT (only if REFUNDTYPE == PARTIAL)
	 */
	
	function refund_transaction($nvp_array)
	{
		global $db;
		
		$nvp_string = $this->nvp_string($nvp_array);
		return $this->api_call('RefundTransaction', $nvp_string);
	}
	
	/**
	 *	When a payment is successful, log the transaction details for 
	 *	archiving or customer service at a later date (refund, void, cancellation, etc).
	 */
	function log_transaction($invoice_id, $result)
	{
		global $db, $config, $user;
		
		$invoice_id = (int) $invoice_id;
		
		$transaction_row = array(
			'invoice_id'			=>	$invoice_id,
			'user_id'				=>	(int) $user->data['user_id'],
			'user_ip'				=>	$_SERVER['REMOTE_ADDR'],
			'transaction_date'		=>	time(),
			'correlation_id'		=>	$result['CORRELATIONID'],
			'transaction_id'		=>	$result['TRANSACTIONID'],
		);
		
		//don't insert the same transaction_id more than once for obvious reasons
		$sql = "SELECT * FROM ".TRANSACTIONS_TABLE." WHERE invoice_id=$invoice_id";
		$result = $db->sql_query($sql);

		if( $db->sql_affectedrows($result) == 0)
		{
			$sql = 'INSERT INTO '.TRANSACTIONS_TABLE.' ' . $db->sql_build_array('INSERT', $transaction_row);
			$result = $db->sql_query($sql);
			$transaction_id = $db->sql_nextid();
			
			return $transaction_id;
			
		}
		
		return -1;
	}
	
	/**
	 *	When a payment profile is created, log the details for archiving and
	 *	administration management at will. This is different from log() because 
	 *	profiles have slightly different parameters to deal with.
	 */
	 
	function log_transaction_profile($invoice_id, $invoice_rate_id, $result)
	{
		global $db, $config, $user;
		
		$invoice_id = (int) $invoice_id;
		$invoice_rate_id = (int) $invoice_rate_id;
		
		$profile_row = array(
			'invoice_id'			=>	$invoice_id,
			'invoice_rate_id'		=>	$invoice_rate_id,
			'user_id'				=>	(int) $user->data['user_id'],
			'user_ip'				=>	$_SERVER['REMOTE_ADDR'],
			'transaction_date'		=>	time(),
			'correlation_id'		=>	$result['CORRELATIONID'],
			'profile_id'			=>	$result['PROFILEID'],
			'profile_status'		=>	$result['PROFILESTATUS'],
		);
		
		$sql = 'INSERT INTO '.TRANSACTION_PROFILES_TABLE.' ' . $db->sql_build_array('INSERT', $profile_row);
		$result = $db->sql_query($sql);
		$profile_id = $db->sql_nextid();
		
		return $profile_id;
		
	}
	
	/**
	 *	CREATE PAYMENT PROFILE
	 *	Create a recurring billing profile
	 *		PARAMS:
	 *		@PAYMENTACTION
	 *		@AMT
	 *		@CREDITCARDTYPE
	 *		@ACCT
	 *		@EXPDATE
	 *		@CVV2
	 *		@FIRSTNAME
	 *		@LASTNAME
	 *		@STREET
	 *		@CITY
	 *		@STATE
	 *		@ZIP
	 *		@COUNTRYCODE
	 *		@CURRENCYCODE
	 *		@PROFILESTARTDATE
	 *		@DESC
	 *		@BILLINGPERIOD
	 *		@BILLINGFREQUENCY
	 *		@TOTALBILLINGCYCLES
	 */
	
	function create_payment_profile($nvp_array)
	{
		global $db;
		
		$nvp_string = $this->nvp_string($nvp_array);
		$this->api_call('CreateRecurringPaymentsProfile', $nvp_string);
	}
	
	/**
	 *	MANAGE RECURRING PAYMENT PROFILE STATUS
	 *	Cancel or suspend a recurring payment.
	 *		PARAMS:
	 *		@PROFILEID
	 *		@ACTION=Cancel|Suspend|Reactivate
	 */
	
	function manage_profile_status($nvp_array)
	{
		global $db;

		$nvp_string = $this->nvp_string($nvp_array);
		return $this->api_call('ManageRecurringPaymentsProfileStatus', $nvp_string);	
	}
	
	function get_profile_details($profile_id)
	{
		global $db;
		
		$nvp_array = array(
			'PROFILEID'		=>	$profile_id,
		);	
		
		$nvp_string = $this->nvp_string($nvp_array);
		return $this->api_call('GetRecurringPaymentsProfileDetails', $nvp_string);
	}
	
	/**
	 *	Parse the error response from PayPal into neatly formatted
	 *	JSON for our form to print for the customer.
	 */
	
	function parse_error($array)
	{
		$response = '<div class="ui-state-error ui-corner-all pad"><ul class="pretty">';
		
		//parse errors into $response
		foreach($result as $error_name => $error_message)
		{
			if( preg_match("/L_LONGMESSAGE\d+/",$error_name) )
			{
				$response .= '<li class="alert">' . $error_message . '</li>';
				$error_count++;
			}
		}
		
		//there was a generic error with the payment
		if(!$error_count)
		{
			$response .= '<li class="alert">There was an unknown error processing your transaction. Please call or email us for immediate assistance!</li>';
		}
		
		$response .= '</ul></div>';
		
		return $response;
	}
	
}

?>