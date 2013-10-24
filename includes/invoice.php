<?php

/**
* @ignore
*/

if (!defined('MY_DASHBOARD'))
{
	exit;
}

/****
 *
 *  @author: jake@litwickimedia.com
 *  @SVN: $Id: invoice.php 49 2010-06-08 14:01:42Z jake $
 *  @Copyright 2009,2010 Litwicki Media LLC
 *
 ***/ 

class invoice
{

	var $subtotal = 0;
	var $discount = 0;
	var $total = 0;
	
	
    public function __construct()
	{
        $this->timestamp = time();
		$this->closed_status = 2;
		$this->open_status = 1;
		$this->approve_status = 2;
		$this->delete_status = 0;	
    }
	
	function add($invoice_row)
	{
		global $db;

		$invoice_row['status'] = 3;

		$sql = 'INSERT INTO '.INVOICES_TABLE.' ' . $db->sql_build_array('INSERT', $invoice_row);
		$result = $db->sql_query($sql);
		$invoice_id = (int) $db->sql_nextid();

		return $invoice_id;
		
	}
	
	function add_recurrence($recurrence_row)
	{
		global $db;
		
		$sql = 'INSERT INTO '.INVOICE_RECURRENCE_TABLE.' ' . $db->sql_build_array('INSERT', $recurrence_row);
		$db->sql_query($sql);
		return true;
	
	}
	
	function publish($invoice_id)
	{
		global $db;
		$sql = "UPDATE ".INVOICES_TABLE." SET status=1 WHERE invoice_id=$invoice_id";
		$db->sql_query($sql);

		return true;
	}
	
	function modify($invoice_id, $invoice_row)
	{
		global $db;
		$sql = 'UPDATE ' . INVOICES_TABLE . ' SET ' . $db->sql_build_array('UPDATE', $invoice_row) . ' WHERE invoice_id = ' . $invoice_id;
		$db->sql_query($sql);

		return true;
	}
	
	function add_hours($invoice_id, $client_id, $invoice_hours)
	{
		global $db;
		
		$sql = "SELECT task_log_id FROM ".INVOICE_HOURS_TABLE." WHERE invoice_id=$invoice_id";
		$result = $db->sql_query($sql);
		while( $row = $db->sql_fetchrow($result) )
		{
			$task_log_array = $row;
		}
		
		//with this invoice_id, link the associated task_log_ids
		foreach($invoice_hours as $task_log_id)
		{
			/**
			 *	Get the hours & rate for this task_log_id
			 */
			
			$sql = "SELECT * FROM
						".TASK_TIMELOG_TABLE." t
						JOIN ".RATES_TABLE." w ON w.rate_id=t.rate_id
					WHERE
						t.task_log_id=$task_log_id";
			
			$result = $db->sql_query($sql);
			$row = $db->sql_fetchrow($result);

			$invoice_hours_row = array(
				'invoice_id'	=>	$invoice_id,
				'task_log_id'	=>	$task_log_id,
				'client_id'		=>	$client_id,
			);
			
			//if the task_log_id isn't already linked, add it
			if( !in_array($task_log_id, $task_log_array) )
			{
				$sql = 'INSERT INTO '.INVOICE_HOURS_TABLE.' ' . $db->sql_build_array('INSERT', $invoice_hours_row);
				$db->sql_query($sql);
			}
		}
		
		//loop through linked task_log_ids to determine if we need to remove any
		foreach($task_log_array as $task_log_id)
		{
			//if the task_log_id isn't linked, delete it
			if( !in_array($task_log_id, $invoice_hours) )
			{
				$db->sql_query("DELETE FROM ".INVOICE_HOURS_TABLE." WHERE invoice_id=$invoice_id AND task_log_id=$task_log_id");
			}
			
			//Do not invoice the same task_log_id more than once
			$sql = "UPDATE ".TASK_HOURS_TABLE." SET status=0 WHERE task_log_id=$task_log_id";
			$db->sql_query($sql);
		 
		}

		return true;
		
	}
	
	function add_rates($invoice_id, $client_id, $invoice_rates)
	{
		global $db;
		
		$sql = "SELECT rate_id FROM ".INVOICE_RATES_TABLE." WHERE invoice_id=$invoice_id";
		$result = $db->sql_query($sql);
		while( $row = $db->sql_fetchrow($result) )
		{
			$existing_rates = $row;
		}
		
		//with this invoice_id, link the associated task_log_ids
		foreach($invoice_rates as $rate_id)
		{
			$invoice_rates_row = array(
				'invoice_id'	=>	$invoice_id,
				'rate_id'		=>	$rate_id,
				'client_id'		=>	$client_id,
			);
			
			//if the rate_id isn't already linked, add it
			if( !in_array($rate_id, $existing_rates) )
			{
				$sql = 'INSERT INTO '.INVOICE_RATES_TABLE.' ' . $db->sql_build_array('INSERT', $invoice_rates_row);
				$db->sql_query($sql);
			}
		}
		
		//loop through linked task_log_ids to determine if we need to remove any
		foreach($existing_rates as $rate_id)
		{
			//if the rate_id isn't linked, delete it
			if( !in_array($rate_id, $invoice_rates) )
			{
				$db->sql_query("DELETE FROM ".INVOICE_RATES_TABLE." WHERE invoice_id=$invoice_id AND rate_id=$rate_id");
			}
			
			//Do not invoice the same rate_id more than once
			$sql = "UPDATE ".TASK_HOURS_TABLE." SET status=0 WHERE rate_id=$rate_id";
			$db->sql_query($sql);
		 
		}

		return true;
	}
	
	/**
	 *	Get rates being billed monthly within this invoice_id
	 *	@purpose: When paying an invoice, we make a lump payment, and then setup a profile for each monthly rate
	 */
	
	function get_rates($invoice_id)
	{
		global $db;
		
		$sql = "SELECT * 
				FROM 
					".INVOICE_RATES_TABLE." ir
					JOIN ".RATES_TABLE." r ON r.rate_id=ir.rate_id 
					JOIN ".INVOICE_RECURRENCE_TABLE." irt ON ir.invoice_rate_id=irt.invoice_rate_id
				WHERE
					ir.invoice_id=$invoice_id";
					
		$result = $db->sql_query($sql);
		
		while( $row = $db->sql_fetchrow($result) )
		{
			$raterow[] = $row;
		}
		
		return $raterow;
		
	}
	
	function delete($invoice_id)
	{
		global $db;
		
		//do not delete an invoice that has been paid!
		$invoice_row = $this->get_detail($invoice_id);
		if($invoice_row['paid'])
		{
			return false;
		}
		
		//delete the invoice
		$sql = "DELETE FROM ".INVOICES_TABLE." WHERE invoice_id=$invoice_id";
		$db->sql_query($sql);
		
		//delete invoice hours
		$sql = "DELETE FROM ".INVOICE_HOURS_TABLE." WHERE invoice_id=$invoice_id";
		$db->sql_query($sql);
		
		//delete invoice rates
		$sql = "DELETE FROM ".INVOICE_RATES_TABLE." WHERE invoice_id=$invoice_id";
		$db->sql_query($sql);
		
		//delete invoice items
		$sql = "DELETE FROM ".INVOICE_ITEMS_TABLE." WHERE invoice_id=$invoice_id";
		$db->sql_query($sql);
		
		//delete invoice discounts
		$sql = "DELETE FROM ".INVOICE_DISCOUNTS_TABLE." WHERE invoice_id=$invoice_id";
		$db->sql_query($sql);
		
		return true;
	}
	
	function invoice_paid($invoice_id)
	{
		global $db;
		
		$invoice_row = array(
			'paid'			=>	1,
			'status_date'	=>	time(),
			'paid_date'		=>	time(),
			'status'		=>	2,
		);
		
		$sql = 'UPDATE ' . INVOICES_TABLE . ' SET ' . $db->sql_build_array('UPDATE', $invoice_row) . ' WHERE invoice_id = ' . $invoice_id;
		$db->sql_query($sql);
	}
	
	/**
	 *	Get invoice detail
	 */
	
	function get_detail($invoice_id, $output = true)
	{
		global $db, $template, $config;
		
		$invoice_id 	= (int) $invoice_id;
		
		$sql = "SELECT 
					i.*, 
					c.company, c.client_id, c.address, c.city, c.state, c.zip, c.phone 
				FROM 
					".INVOICES_TABLE." i 
					LEFT JOIN ".CLIENTS_TABLE." c ON c.client_id=i.client_id 
				WHERE 
					i.invoice_id=$invoice_id";
							
		$result 		= $db->sql_query($sql);
		$invoice_row 	= $db->sql_fetchrow($result);

		$client_id = (int) $invoice_row['client_id'];

		$invoice_hours = $this->get_hours($invoice_id);
		$invoice_rates = $this->get_invoice_rates($invoice_id);
		$invoice_items = $this->get_invoice_items($invoice_id);
		$invoice_discounts = $this->get_invoice_discounts($invoice_id);

		$invoice_row['discount']			= '$' . number_format($this->discount, 2);
		$invoice_row['subtotal'] 			= '$' . number_format($this->subtotal, 2);
		
		$invoice_row['total'] 				= '$' . number_format($this->subtotal - $this->discount, 2);
		
		$invoice_row['comments'] 			= litwicki_decode($invoice_row['comments']);
		$invoice_row['invoice_unique_id']	= $invoice_row['date_added'] . '-' . $invoice_row['invoice_id'];
		$invoice_row['author']				= get_user_val('user_realname', $invoice_row['user_id']);
		$invoice_row['invoice_date']		= date($config['date_short'], $invoice_row['invoice_date']);
		$invoice_row['invoice_date_due']	= date($config['date_short'], $invoice_row['invoice_date_due']);
		$invoice_row['status_date']			= date($config['date_long'], $invoice_row['status_date']);
		$invoice_row['date_added']			= date($config['date_long'], $invoice_row['date_added']);
		$invoice_row['invoice_id']			= $invoice_row['invoice_id'];
		
		$invoice_row = array_change_key_case($invoice_row, CASE_UPPER);

		if($output)
		{
			$template->assign($invoice_row);
			$template->assign(array(
				'SHOW_INVOICE_RATES'		=>	count($invoice_discounts) > 0 ? true : false,
				'SHOW_INVOICE_HOURS'		=>	count($invoice_hours) > 0 ? true : false,
				'SHOW_INVOICE_ITEMS'		=>	count($invoice_items) > 0 ? true : false,
				'SHOW_INVOICE_DISCOUNTS'	=>	count($invoice_discounts) > 0 ? true : false,
			));
		}
		
		$db->sql_freeresult($result);
		
		return $invoice_row;
		
	}
	
	function get_invoice_discounts($invoice_id, $output = true)
	{
		global $db, $template;
	
		$sql = "SELECT d.* 
				FROM
					".INVOICES_TABLE." i 
					JOIN ".INVOICE_DISCOUNTS_TABLE." d ON d.invoice_id=i.invoice_id
				WHERE
					d.invoice_id=$invoice_id 
				ORDER BY
					d.discount_id";

		$result = $db->sql_query($sql);
		while( $row = $db->sql_fetchrow($result) )
		{
			$this->discount += $row['discount_amount'];
			$row['discount_amount'] = '$' . number_format($row['discount_amount'],2);
			$invoice_discounts_array[] = array_change_key_case($row, CASE_UPPER);
		}
		
		if($output)
		{
			$template->assign('invoice_discounts_row', $invoice_discounts_array);
		}
		
		$db->sql_freeresult($result);

		return $invoice_discounts_array;
		
	}
	
	function get_invoice_items($invoice_id, $output = true)
	{
		global $db, $template;
	
		$sql = "SELECT b.* 
				FROM
					".INVOICES_TABLE." i 
					JOIN ".INVOICE_ITEMS_TABLE." b ON b.invoice_id=i.invoice_id
				WHERE
					b.invoice_id=$invoice_id 
				ORDER BY
					b.item_id";
					
		$result = $db->sql_query($sql);
		while( $row = $db->sql_fetchrow($result) )
		{
			$this->subtotal += $row['item_price'];
			$row['item_price'] = '$' . number_format($row['item_price'],2);
			$invoice_items_array[] = array_change_key_case($row, CASE_UPPER);
		}
		
		if($output)
		{
			$template->assign('invoice_items_row', $invoice_items_array);
		}
		
		$db->sql_freeresult($result);

		return $invoice_items_array;
		
	}
	
	function get_invoice_rates($invoice_id, $output = true)
	{
		global $db, $template;
		
		$invoice_id = (int) $invoice_id;
		
		//get services linked to this invoice
		$sql = "SELECT *
				FROM
					".INVOICES_TABLE." i 
					LEFT JOIN ".INVOICE_RATES_TABLE." ir ON ir.invoice_id=i.invoice_id 
					LEFT JOIN ".RATES_TABLE." r ON r.rate_id=ir.rate_id 
					LEFT JOIN ".INVOICE_RECURRENCE_TABLE." x ON x.invoice_rate_id=ir.invoice_rate_id 
				WHERE
					i.invoice_id=$invoice_id";

		$result = $db->sql_query($sql);
		while( $row = $db->sql_fetchrow($result) )
		{
			$this->subtotal += $row['cost'];
			$row['cost'] = '$' . number_format($row['cost'],2);
			
			$row['start_date'] 		= $row['start_date'] 	== 0 ? 0 : date($config['date_short'], $row['start_date']);
			$row['end_date'] 		= $row['end_date'] 		== 0 ? 0 : date($config['date_short'], $row['end_date']);
			$row['interval_days'] 	= (int) $row['interval_days'];
			
			$invoice_rates_array[] = array_change_key_case($row, CASE_UPPER);
			unset($row);
		}
		
		if($output)
		{
			$template->assign('invoice_rates_row', $invoice_rates_array);
		}
		
		$db->sql_freeresult($result);
	
		return $invoice_rates_array;
	}
	
	function get_hours($invoice_id, $output = true)
	{
		global $db, $template;
		
		$invoice_id = (int) $invoice_id;
		
		//get the hours associated with this invoice
		$sql = "SELECT
					t.task_id, t.task_name, 
					tl.task_log_id, tl.user_id, tl.date_added, tl.minutes, tl.minutes/60 as hours, tl.work_description, tl.work_date,
					r.cost,
					c.* 
				FROM 
					".INVOICES_TABLE." i 
					LEFT JOIN ".INVOICE_HOURS_TABLE." ih ON ih.invoice_id=i.invoice_id 
					JOIN ".TASK_TIMELOG_TABLE." tl ON tl.task_log_id=ih.task_log_id 
					JOIN ".TASKS_TABLE." t ON t.task_id=tl.task_id 
					JOIN ".RATES_TABLE." r ON r.rate_id=tl.rate_id 
					JOIN ".CLIENTS_TABLE." c ON c.client_id=i.client_id 
				WHERE 
					i.invoice_id=$invoice_id";

		$result = $db->sql_query($sql);

		while( $row = $db->sql_fetchrow($result) )
		{
			$work_cost = ($row['minutes'] > 0 ? $row['cost'] * round(($row['minutes'] / 60), 2) : $row['cost'] );
			//tally up hours subtotal -- only multiply by hours if there are hours :)
			$this->subtotal += $work_cost;
			
			$row['WORK_DATE']			= date('m/d/Y', $row['work_date']);
			$row['WORK_DESCRIPTION']	= litwicki_decode($row['work_description']);
			$row['WORK_RATE']			= '$' . number_format($row['cost'], 2);
			$row['WORK_HOURS']			= number_format($row['hours'], 2);
			$row['WORK_TITLE']			= litwicki_decode($row['task_name']);
			$row['SUBTOTAL']			= '$' . number_format($work_cost, 2);
			$invoice_hours_array[] 		= array_change_key_case($row, CASE_UPPER);
			
			unset($row);
			
		}
		
		$db->sql_freeresult($result);
		
		if($output)
		{
			$template->assign('invoice_hours_row', $invoice_hours_array);
		}
		
		return $invoice_hours_array;
	}
	
	/**
	 *	Get unpaid hours for a particular project_id
	 */
	function unpaid_hours($client_id)
	{
		global $db, $template;
		
		$client_id = (int) $client_id;
		
		$sql = "SELECT 
					p.project_id,
					t.task_id, t.task_name, 
					h.task_log_id, h.user_id, h.date_added, h.minutes, h.work_description, h.work_date, h.status, 
					r.description AS work_type, r.cost  
				FROM 
					".PROJECTS_TABLE." p
					JOIN ".TASKS_TABLE." t ON t.project_id=p.project_id
					JOIN ".TASK_TIMELOG_TABLE." h ON h.task_id=t.task_id
					LEFT JOIN ".RATES_TABLE." r ON r.rate_id=h.rate_id
				WHERE
					p.client_id = $client_id 
					AND h.status = $this->open_status
					AND h.task_log_id NOT IN(SELECT task_log_id FROM ".INVOICE_HOURS_TABLE.")";

		$result = $db->sql_query($sql);

		while( $row = $db->sql_fetchrow($result) )
		{
			$hours = round(($row['minutes'] / 60), 2);
			$total_hours += $hours;
			
			$row['worker_name']			=	get_user_val('user_realname', $row['user_id']);
			$row['work_date']			=	date('m/d/Y', $row['work_date']);
			$row['work_description']	=	litwicki_decode($row['work_description']);
			$row['work_hours']			=	$hours;
			$row['item_cost']			=	$hours * $row['cost'];
				
			$timelogrow_array[] = array_change_key_case($row, CASE_UPPER);
		}

		$template->assign('timelogrow', $timelogrow_array);

		return $total_hours;
	}

	/**
	 *	Display all invoices for staff, or all accessible invoices
	 *	for a client. Do not display invoices with status (3) because
	 *	that is an invoice in progress.
	 */
	function myinvoices($paid_status = false, $client_id = false, $past_due = false)
	{
		global $db, $template, $auth, $config;
		
		$now = time();
		
		if($client_id)
		{
			$sql = "SELECT 
					DISTINCT i.invoice_id
				FROM 
					".INVOICES_TABLE." i
					JOIN ".CLIENTS_TABLE." c ON c.client_id=i.client_id
					LEFT JOIN ".PROJECTS_TABLE." p ON p.client_id=i.client_id
				WHERE 
					invoice_id > 0 
					AND c.client_id=$client_id "
					. (is_numeric($paid_status) ? " AND paid=$paid_status" : '')
					. ($past_due == true ? " AND invoice_date_due < $now" : '');
		}
		else
		{
			if( !$auth->user_group['S_STAFF'] )
			{
				$sql = "SELECT 
							DISTINCT i.invoice_id  
						FROM ".INVOICES_TABLE." i
							JOIN ".CLIENTS_TABLE." c ON c.client_id=p.client_id 
							JOIN ".CLIENT_USERS_TABLE." cu ON cu.client_id=c.client_id 
						WHERE 
							cu.user_id=$user_id
							AND i.status <> 3 "
							. (is_numeric($paid_status) ? " AND i.paid=$paid_status" : '')
							. ($past_due == true ? " AND invoice_date_due < $now" : '');
			}
			else
			{
				$sql = "SELECT 
							DISTINCT invoice_id 
						FROM 
							".INVOICES_TABLE." 
						WHERE 
							invoice_id > 0 "
							. (is_numeric($paid_status) ? " AND paid=$paid_status" : '')
							. ($past_due == true ? " AND invoice_date_due < $now" : '');
			}
		}
		
		$sql .= " ORDER BY invoice_id DESC";

		$result = $db->sql_query($sql);
		$invoice_count = $db->sql_affectedrows($result);

		while( $row = $db->sql_fetchrow($result) )
		{
			$invoice_row = $this->get_detail($row['invoice_id'], $output = false);
			$invoicerow_array[] = array_change_key_case($invoice_row, CASE_UPPER);
		}
		
		$template->assign('invoicerow',$invoicerow_array);

		return $invoice_count;
	}
	
	function add_items($invoice_id, $invoice_items)
	{
		global $db;
		
		$invoice_id = (int) $invoice_id;
		
		if(empty($invoice_items) || !$invoice_id)
		{
			return false;
		}
		
		foreach($invoice_items as $item_row)
		{
			$sql = 'INSERT INTO '.INVOICE_ITEMS_TABLE.' ' . $db->sql_build_array('INSERT', $item_row);
			$result = $db->sql_query($sql);
			$item_id = (int) $db->sql_nextid();
			
			unset($item_row);
		}
		
	}
	
	function add_discounts($invoice_id, $discount_items)
	{
		global $db;
		
		$invoice_id = (int) $invoice_id;
		
		if(empty($discount_items) || !$invoice_id)
		{
			return false;
		}
		
		foreach($discount_items as $discount_row)
		{
			$sql = 'INSERT INTO '.INVOICE_DISCOUNTS_TABLE.' ' . $db->sql_build_array('INSERT', $discount_row);
			$result = $db->sql_query($sql);
			$discount_id = (int) $db->sql_nextid();
			
			unset($discount_row);
		}
		
	}
	
	
	
}
?>
