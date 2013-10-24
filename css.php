<?php

/****
 *
 *  @author: jake@litwickimedia.com
 *  @SVN: $Id: css.php 41 2010-06-04 16:56:54Z jake $
 *  @Copyright 2009,2010 Litwicki Media LLC
 *
 ***/
 
define('MY_DASHBOARD', true);
$root_path = './';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($root_path . 'common.' . $phpEx);

//get dashboard style
ob_start();
include($root_path . 'css/style.css');
$stylesheet = ob_get_contents();
ob_end_clean();

//append ui.css for jqueryui elements
ob_start();
include($root_path . 'css/ui.css');
$ui_css = ob_get_contents();
ob_end_clean();

$stylesheet = $stylesheet . $ui_css;

$sql = "SELECT * FROM ".CONFIG_TABLE." WHERE theme_config=1";
$result = $db->sql_query($sql);
while( $row = $db->sql_fetchrow($result) )
{
	$theme_config_name = '{' . strtoupper($row['config_name']) . '}';
	$theme_config_value = $row['config_value'];
	
	//replace stuff
	$stylesheet = str_replace($theme_config_name, $theme_config_value, $stylesheet);
}

$db->sql_freeresult($result);

header('Content-type: text/css; charset=UTF-8');
echo $stylesheet;

exit;

?>