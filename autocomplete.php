<?php

/****
 *
 *  @author: jake@litwickimedia.com
 *  @SVN:	$Id$
 *  @Copyright 2009,2010 Litwicki Media LLC
 *
 ***/
 
define('MY_DASHBOARD', true);
$root_path = './';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($root_path . 'common.' . $phpEx);

$type = $_GET['type'];

if( $type == 'invoice' )
{
	$sql = "SELECT 
				c.client_id as value, c.company,
				(SELECT COUNT(invoice_id) FROM ".INVOICES_TABLE." WHERE client_id=c.client_id) AS invoice_count 
			FROM 
				".CLIENTS_TABLE." c
			WHERE 
				(
					company like '%" . sanitize($_GET['term']) . "%' 
					OR company like '%" . ucwords(sanitize($_GET['term'])) . "%' 
				AND client_id IN(SELECT client_id FROM ".INVOICES_TABLE.")
				)";

	$result = $db->sql_query($sql);
	if( $db->sql_affectedrows($result) > 0 )
	{
		while( $row = $db->sql_fetchrow($result) )
		{
			$row['label'] = 'Client #' . $row['value'] . ' - ' . $row['company'] . ' (' . $row['invoice_count'] . ')';
			$autocomplete[] = $row;
		}
	}
	else
	{
		$row['label'] = 'No matches...';
		$row['value'] = '0';
		$autocomplete[] = $row;
	}
}

if( $type == 'clientuser' || $type == 'messageuser' )
{
	$sql = "SELECT 
				user_id, user_realname
			FROM
				".USERS_TABLE."
			WHERE
				user_realname like '%" . sanitize($_GET['term']) . "%' 
				OR user_realname like '%" . ucwords(sanitize($_GET['term'])) . "%'";

	$result = $db->sql_query($sql);
	
	if( $db->sql_affectedrows($result) > 0 )
	{
		while( $row = $db->sql_fetchrow($result) )
		{
			$row['label'] = $row['user_realname'] . ' (' . $row['user_id'] . ')';
			$row['value'] = $row['user_id'];
			$autocomplete[] = $row;
		}
	}
	else
	{
		$row['label'] = 'No matches...';
		$row['value'] = '0';
		$autocomplete[] = $row;
	}
}

if(!empty($autocomplete))
{
	echo json_encode($autocomplete);
}

exit;
?>