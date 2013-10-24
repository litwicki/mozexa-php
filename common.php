<?php

/****
 *
 *  @author: jake@litwickimedia.com
 *  @SVN: $Id: common.php 42 2010-06-06 01:40:48Z jake $
 *  @Copyright 2009,2010 Litwicki Media LLC
 *
 ***/

if (!defined('MY_DASHBOARD'))
{
	exit;
}

$starttime = explode(' ', microtime());
$starttime = $starttime[1] + $starttime[0];

// Report all errors, except notices
error_reporting(E_ALL);

require($root_path . 'config.' . $phpEx);

// Include files
require($root_path . 'includes/constants.' . $phpEx);
include($root_path . 'includes/functions.' . $phpEx);
require($root_path . 'includes/smarty/Smarty.class.' . $phpEx);
require($root_path . 'includes/dashboard.' . $phpEx);
require($root_path . 'includes/dbal.' . $phpEx);
require($root_path . 'includes/user.' . $phpEx);
require($root_path . 'includes/auth.' . $phpEx);
require($root_path . 'includes/file.' . $phpEx);

// Set PHP error handler to ours
set_error_handler('msg_handler');

$dashboard = new dashboard();

$file = new file();

$db = new dbal();
$db->sql_connect($dbhost, $dbuser, $dbpasswd, $dbname, $dbport, false, false);

$user = new user();
$auth = new auth();

//setup global config values
global $config;
$sql = "SELECT config_name, config_value FROM ".CONFIG_TABLE;
$result = $db->sql_query($sql);
while( $row = $db->sql_fetchrow($result) )
{
	$config[$row['config_name']] = preg_match('/^paypal_.*/',$row['config_name']) ? decrypt($row['config_value']) : $row['config_value'];
}

//are we on test or www?
$this_url 				=	explode('.', $_SERVER['HTTP_HOST']);
$base_url 				=	'http://' . $_SERVER["HTTP_HOST"];

$cookie_domain 			=	$_SERVER["SERVER_NAME"];

//Setup the template system
$template = new Smarty;

$template->template_dir = $root_path . 'template';
$template->compile_dir = $root_path . 'template_c';
$template->cache_dir = $root_path . 'cache';
$template->caching = 0;

//base status variables
$delete_status 		= 0;
$open_status 		= 1;
$complete_status 	= 2;
$pending_status 	= 3;

?>
