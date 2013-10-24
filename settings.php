<?php 

/****
 *
 *  @author: jake@litwickimedia.com
 *  @SVN: $Id: settings.php 45 2010-06-06 22:21:29Z jake $
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

if( !$auth->user_group['S_ADMINISTRATOR'] )
{
	login_box("$base_url/settings.php", 'Administrators Only!');
}

if( isset($_POST['save']) )
{
	$config_settings = $_POST;

	foreach($config_settings as $config_name => $config_value)
	{
		if( preg_match('/^paypal_.*/',$config_name) )
		{
			/**
			 * Set the paypal configs absolutely last in case
			 * the encryption key is changed, so these values
			 * are re-encrypted with the new key as intended.
			 */
			 $paypal[] = $config_value;
		}
		
		set_config($config_name, $config_value);
	}
	
	foreach($paypal as $config_name => $config_value)
	{
		$config_value = encrypt($config_value);
		set_config($config_name, $config_value);
	}

	redirect("$base_url/settings.php");
}
elseif( isset($_POST['savelogo']) )
{
	if( isset($_FILES['logo_file']) )
	{
		$logo = $_FILES['logo_file'];

		$oldfile = $logo['tmp_name'][0];

		if( filesize($oldfile) <= 2000000 )
		{
			$filename = $logo['name'][0];
			
			$error_filename = $filename;
			$filename = strtolower(str_replace(" ","_",$filename));
			
			$file_ext = preg_replace("/^.*\.(.*?)$/","$1",$filename);

			$config_file = '/images/logos/logo_' . time() . '.' . $file_ext;
			
			$newfile = FILE_PATH . $config_file;

			if( @move_uploaded_file($oldfile, $newfile) )
			{
				$success = 'Logo updated successfully!';
				$error = '';
				
				set_config('logo_file', $config_file);
				
				//resize logo
				$cmd = 'convert ' . $newfile . ' -resize 200x ' . $newfile;
				@exec($cmd);
				
				redirect("$base_url/settings.php");
			}
			else
			{
				$logo_error = 'Error uploading file: ' . $error_filename . '!';
			}
		}
		else
		{
			$logo_error = 'Please make sure the logo image is no larger than 2MB.';
		}
	}
	else
	{
		$logo_error = 'I cannot upload a blank file!';
	}
}
elseif( isset($_POST['saverate']) )
{
	$rate_id = (int) $_POST['rate_id'];
	
	$rate_row = array(
		'name'			=>	sanitize($_POST['rate_name']),
		'description'	=>	sanitize($_POST['rate_description']),
		'cost'			=>	$_POST['rate_cost'],
		'hourly'		=>	$_POST['hourly'],
	);

	if($rate_id)
	{
		$sql = 'UPDATE ' . RATES_TABLE . ' SET ' . $db->sql_build_array('UPDATE', $rate_row) . ' WHERE rate_id = ' . (int) $rate_id;
		$db->sql_query($sql);
	}
	else
	{
		$new_rate = true;
		$sql = 'INSERT INTO '.RATES_TABLE.' ' . $db->sql_build_array('INSERT', $rate_row);
		$result = $db->sql_query($sql);
		$rate_id = $db->sql_nextid();
	}
	
	$rate_row = $dashboard->rate_detail($rate_id);
	$rate_row['new_rate'] = $new_rate;
	
	$json_row = json_encode($rate_row);
	echo $json_row;
	exit;
	
}
elseif( isset($_POST['deleterate']) )
{
	$rate_id = (int) $_POST['rate_id'];
	$sql = "UPDATE ".RATES_TABLE." SET status=0 WHERE rate_id=$rate_id";
	$db->sql_query($sql);
	
	$rate_row = $dashboard->rate_detail($rate_id);
	$json_row = json_encode($rate_row);
	echo $json_row;
	exit;

}
elseif( isset($_POST['rate_id']) )
{
	$rate_id = (int) $_POST['rate_id'];
	$rate_row = $dashboard->rate_detail($rate_id);
	
	$json_row = json_encode($rate_row);
	echo $json_row;
	exit;
}
else
{
	//build configs
	$sql = "SELECT * FROM ".CONFIG_TABLE." WHERE db_config=1 AND payment_config=0 AND theme_config=0 ORDER BY settings_order, config_name";
	$result = $db->sql_query($sql);

	while( $row = $db->sql_fetchrow($result) )
	{
		$config_name_clean = ucwords(str_replace("_"," ",$row['config_name']));
		
		$config_row = array(
			'CONFIG_NAME'			=>	$row['config_name'],
			'CONFIG_NAME_CLEAN'		=>	$config_name_clean,
			'CONFIG_VALUE'			=>	$row['config_value'],
			'CONFIG_DESCRIPTION'	=>	$row['config_description'],
			'IS_WYSIWYG'			=>	$row['is_wysiwyg'],
		);
		
		//ass backwards to make the ddl-states dropdown work
		if( $row['config_name'] == 'company_state' )
		{
			$template->assign(array(
				'STATE'		=>	$row['config_value'],
			));
		}
		
		$config_row_array[] = $config_row;
	}

	$template->assign('configrow', $config_row_array);
	
	//separate paypal config values for added importance
	$sql = "SELECT * FROM ".CONFIG_TABLE." WHERE payment_config=1 ORDER BY settings_order";
	$result = $db->sql_query($sql);

	while( $row = $db->sql_fetchrow($result) )
	{
		$config_name_clean = ucwords(str_replace("_"," ",$row['config_name']));

		$config_row = array(
			'CONFIG_NAME'			=>	$row['config_name'],
			'CONFIG_NAME_CLEAN'		=>	$config_name_clean,
			'CONFIG_VALUE'			=>	preg_match('/^paypal_.*/',$row['config_name']) ? decrypt($row['config_value']) : $row['config_value'],
			'CONFIG_DESCRIPTION'	=>	$row['config_description'],
			'IS_WYSIWYG'			=>	$row['is_wysiwyg'],
		);

		$paypal_config[] = $config_row;
	}

	$template->assign('payment_configrow', $paypal_config);

	//separate theme config values for added importance
	$sql = "SELECT * FROM ".CONFIG_TABLE." WHERE theme_config=1 ORDER BY settings_order";
	$result = $db->sql_query($sql);

	while( $row = $db->sql_fetchrow($result) )
	{
		$config_name_clean = ucwords(str_replace("_"," ",$row['config_name']));

		$config_row = array(
			'CONFIG_NAME'			=>	$row['config_name'],
			'CONFIG_NAME_CLEAN'		=>	$config_name_clean,
			'CONFIG_VALUE'			=>	$row['config_value'],
			'CONFIG_DESCRIPTION'	=>	$row['config_description'],
			'IS_WYSIWYG'			=>	$row['is_wysiwyg'],
		);

		$theme_config[] = $config_row;
	}

	$template->assign('theme_configrow', $theme_config);
	
	//build staff user list
	$dashboard->myusers($staff_only = true);

	//build list of companies
	$template->assign(array(
		'S_MY_DASHBOARD'		=>	true,
		'LOGO_ERROR'			=>	$logo_error,
		'S_SETTINGS'			=>	true,
	));

}
//spit out the page
page_header("Dashboard Settings");

$template->display('template/dashboard_settings.html');

page_footer();

?>