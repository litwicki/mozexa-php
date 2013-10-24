<?php

if (!defined('MY_DASHBOARD'))
{
	exit;
}

require('phpmailer/class.phpmailer.php');

/****
 *
 *  @author: jake@litwickimedia.com
 *  @SVN: $Id: functions.php 42 2010-06-06 01:40:48Z jake $
 *  @Copyright 2009,2010 Litwicki Media LLC
 *
 ***/ 

//alias for sanitize to be more name relevant
function litwicki_cleanstr($string)
{
	return sanitize($string);
}

function sanitize($string)
{
	$string = normalize($string);
	$string = htmlentities($string, ENT_QUOTES, "UTF-8", false);
	$string = fix_encoding($string);
	return $string;
}

function litwicki_decode($string)
{
	$string = html_entity_decode($string);
	$string = str_replace("\'","'",$string); //shouldn't ever be used with utf8 encoding but fallback just in case
	$string = str_replace('\"','"',$string); //shouldn't ever be used with utf8 encoding but fallback just in case
	$string = fix_encoding($string);
	return $string;
}

function str_decode($string)
{
	return litwicki_decode($string);
}

function fix_encoding($str)
{
	$cur_encoding = mb_detect_encoding($str);
	if($cur_encoding == "UTF-8" && mb_check_encoding($str,"UTF-8"))
	{
		return $str;
	}
	else
	{
		return utf8_encode($str);
	}
}

function normalize($string) 
{
    $table = array(
        'Š'=>'S', 'š'=>'s', 'Đ'=>'Dj', 'đ'=>'dj', 'Ž'=>'Z', 'ž'=>'z', 'Č'=>'C', 'č'=>'c', 'Ć'=>'C', 'ć'=>'c',
        'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
        'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O',
        'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss',
        'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e',
        'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o',
        'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'ý'=>'y', 'þ'=>'b',
        'ÿ'=>'y', 'Ŕ'=>'R', 'ŕ'=>'r',
    );
   
    return strtr($string, $table);
}

//$attachments array() is passed through to email_user
function email_staff($subject, $message, $html_email = false, $attachments = false)
{
	global $db, $staff_group_id;
	
	$message = litwicki_decode($message);
	$subject = litwicki_decode($subject);
	
	$sql = "SELECT u.user_id FROM ".USERS_TABLE." u JOIN ".USER_GROUP_TABLE." g ON g.user_id=u.user_id WHERE g.group_id=$staff_group_id";
	$result = $db->sql_query($sql);
	while( $row = $db->sql_fetchrow($result) )
	{
		email_user($subject, $message, $row['user_id'], $html_email, $priority = 5, $attachments);
	}
	
	$db->sql_freeresult($result);

	return false;
	
}

function notify_staff($subject, $message, $priority = 3, $html_email = false)
{
	global $config;

	$mail = new PHPMailer(TRUE);
	
    $mail->SetFrom($config['staff_email'], $config['dashboard_name']);
    $mail->Subject = $subject;
	
	$mail->AddAddress($config['staff_email'], $config['company_name']);

    //high/normal/low/1/3/5
    $mail->Priority = $priority;

	if($html_email)
	{
		$mail->MsgHTML($message);
		$mail->AltBody = $message;
	}
	else
	{
		$mail->Body = $message;
	}

	//send the email
	$mail->Send();
	
	return true;
}

function email_user($subject, $message, $user_id, $html_email = false, $priority = 3, $attachments = false)
{
	global $db, $config, $base_url, $root_path;
	
	if( !$user_id )
	{
		return false;
	}
	
	//append global signature to outgoing emails
	$signature = $config['email_signature'];

	if(!$html_email)
	{
		$signature = preg_replace('/<br(\s+)?\/?>/i', "\n", $signature);
		$signature = strip_tags($signature);
	}
	
	$message = $message . $signature;

	$sql = "SELECT * FROM ".USERS_TABLE." WHERE user_id=$user_id";
	$result = $db->sql_query($sql);
	$row = $db->sql_fetchrow($result);
	
	$user_email = $row['user_email'];
	$user_realname = $row['user_realname'];
	$db->sql_freeresult($result);

	$mail = new PHPMailer(TRUE);

	//$mail->AddReplyTo($config['staff_email'], $config['dashboard_name']);
	$mail->AddAddress($user_email, $user_realname);
	$mail->SetFrom($config['staff_email'], $config['dashboard_name']);
	//$mail->AddReplyTo('name@yourdomain.com', 'First Last');
	
	$mail->Subject = $subject;
		
	if($html_email)
	{
		$mail->MsgHTML($message);
		$mail->AltBody = $message;
	}
	else
	{
		$mail->Body = $message;
	}

	//are there any attachments?
	if( !empty($attachments) )
	{
		foreach($attachments as $attachment_id)
		{
			$sql = "SELECT * FROM ".LITWICKI_ATTACHMENTS_TABLE." WHERE attachment_id=$attachment_id";
			$result = $db->sql_query($sql);
			$row = $db->sql_fetchrow($result);
			
			$filename = $row['filename'];
			$unique_filename = $row['unique_filename'];
			
			$file_ext = $row['file_exit'];
			
			$real_filename = "$root_path/files/$unique_filename";
			$temp_filename = "$root_path/files/tmp/$filename";
			
			//make a temp copy to attach
			if( copy($real_filename, $temp_filename) )
			{
				// Attach the file, assigning it a name and a corresponding Mime-type.
				$mail->AddAttachment($temp_filename);
			}
		}
	}

	//send the email
	$mail->Send();
	
	//delete the temp file if one exists
	if( file_exists($temp_filename) )
	{
		unlink($temp_filename);
	}
	
	return true;
}

//thanks: http://www.webcheatsheet.com/PHP/get_current_page_url.php
function thisurl()
{
	$url = 'http';
	
	if ($_SERVER['HTTPS'] == "on") 
	{ 
		$url .= "s"; 
	}
	
	$url .= "://";
	
	if ($_SERVER['SERVER_PORT'] != "80") 
	{
		$url .= $_SERVER['SERVER_NAME'].":".$_SERVER['SERVER_PORT'].$_SERVER['REQUEST_URI'].$_SERVER['QUERY_STRING'];
	} 
	else 
	{
		$url .= $_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];
	}
	
	return $url;
}

function filesize_format($fs) 
{ 
	$bytes = array('KB', 'KB', 'MB', 'GB', 'TB'); 
	
	// values are always displayed in at least 1 kilobyte: 
	if ($fs <= 999)
	{ 
		$fs = 1; 
	} 
	
	for ($i = 0; $fs > 999; $i++)
	{ 
		$fs /= 1024; 
	} 
	
	//return array(ceil($fs), $bytes[$i]);
	return ceil($fs) . " " . $bytes[$i];
} 

//return mime-type of specific file extension
function mime_type($extension)
{
	$mime_type = array(
		'ai '		=>		'application/postscript',
		'aif'		=>		'audio/x-aiff',
		'aifc'		=>		'audio/x-aiff',
		'aiff'		=>		'audio/x-aiff',
		'asc'		=>		'text/plain',
		'atom'		=>		'application/atom+xml',
		'au'		=>		'audio/basic',
		'avi'		=>		'video/x-msvideo',
		'bcpio'		=>		'application/x-bcpio',
		'bin'		=>		'application/octet-stream',
		'bmp'		=>		'image/bmp',
		'cdf'		=>		'application/x-netcdf',
		'cgm'		=>		'image/cgm',
		'class'		=>		'application/octet-stream',
		'cpio'		=>		'application/x-cpio',
		'cpt'		=>		'application/mac-compactpro',
		'csh'		=>		'application/x-csh',
		'css'		=>		'text/css',
		'dcr'		=>		'application/x-director',
		'dif'		=>		'video/x-dv',
		'dir'		=>		'application/x-director',
		'djv'		=>		'image/vnd.djvu',
		'djvu'		=>		'image/vnd.djvu',
		'dll'		=>		'application/octet-stream',
		'dmg'		=>		'application/octet-stream',
		'dms'		=>		'application/octet-stream',
		'doc'		=>		'application/msword',
		'docx'		=>		'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'dtd'		=>		'application/xml-dtd',
		'dv'		=>		'video/x-dv',
		'dvi'		=>		'application/x-dvi',
		'dxr'		=>		'application/x-director',
		'eps'		=>		'application/postscript',
		'etx'		=>		'text/x-setext',
		'exe'		=>		'application/octet-stream',
		'ez'		=>		'application/andrew-inset',
		'gif'		=>		'image/gif',
		'gram'		=>		'application/srgs',
		'grxml'		=>		'application/srgs+xml',
		'gtar'		=>		'application/x-gtar',
		'hdf'		=>		'application/x-hdf',
		'hqx'		=>		'application/mac-binhex40',
		'htm'		=>		'text/html',
		'html'		=>		'text/html',
		'ice'		=>		'x-conference/x-cooltalk',
		'ico'		=>		'image/x-icon',
		'ics'		=>		'text/calendar',
		'ief'		=>		'image/ief',
		'ifb'		=>		'text/calendar',
		'iges'		=>		'model/iges',
		'igs'		=>		'model/iges',
		'jnlp'		=>		'application/x-java-jnlp-file',
		'jp2'		=>		'image/jp2',
		'jpe'		=>		'image/jpeg',
		'jpeg'		=>		'image/jpeg',
		'jpg'		=>		'image/jpeg',
		'js'		=>		'application/x-javascript',
		'kar'		=>		'audio/midi',
		'latex'		=>		'application/x-latex',
		'lha'		=>		'application/octet-stream',
		'lzh'		=>		'application/octet-stream',
		'm3u'		=>		'audio/x-mpegurl',
		'm4a'		=>		'audio/mp4a-latm',
		'm4b'		=>		'audio/mp4a-latm',
		'm4p'		=>		'audio/mp4a-latm',
		'm4u'		=>		'video/vnd.mpegurl',
		'm4v'		=>		'video/x-m4v',
		'mac'		=>		'image/x-macpaint',
		'man'		=>		'application/x-troff-man',
		'mathml'	=>		'application/mathml+xml',
		'me'		=>		'application/x-troff-me',
		'mesh'		=>		'model/mesh',
		'mid'		=>		'audio/midi',
		'midi'		=>		'audio/midi',
		'mif'		=>		'application/vnd.mif',
		'mov'		=>		'video/quicktime',
		'movie'		=>		'video/x-sgi-movie',
		'mp2'		=>		'audio/mpeg',
		'mp3'		=>		'audio/mpeg',
		'mp4'		=>		'video/mp4',
		'mpe'		=>		'video/mpeg',
		'mpeg'		=>		'video/mpeg',
		'mpg'		=>		'video/mpeg',
		'mpga'		=>		'audio/mpeg',
		'ms'		=>		'application/x-troff-ms',
		'msh'		=>		'model/mesh',
		'mxu'		=>		'video/vnd.mpegurl',
		'nc'		=>		'application/x-netcdf',
		'oda'		=>		'application/oda',
		'ogg'		=>		'application/ogg',
		'pbm'		=>		'image/x-portable-bitmap',
		'pct'		=>		'image/pict',
		'pdb'		=>		'chemical/x-pdb',
		'pdf'		=>		'application/pdf',
		'pgm'		=>		'image/x-portable-graymap',
		'pgn'		=>		'application/x-chess-pgn',
		'pic'		=>		'image/pict',
		'pict'		=>		'image/pict',
		'png'		=>		'image/png',
		'pnm'		=>		'image/x-portable-anymap',
		'pnt'		=>		'image/x-macpaint',
		'pntg'		=>		'image/x-macpaint',
		'ppm'		=>		'image/x-portable-pixmap',
		'ppt'		=>		'application/vnd.ms-powerpoint',
		'ps'		=>		'application/postscript',
		'qt'		=>		'video/quicktime',
		'qti'		=>		'image/x-quicktime',
		'qtif'		=>		'image/x-quicktime',
		'ra'		=>		'audio/x-pn-realaudio',
		'ram'		=>		'audio/x-pn-realaudio',
		'ras'		=>		'image/x-cmu-raster',
		'rdf'		=>		'application/rdf+xml',
		'rgb'		=>		'image/x-rgb',
		'rm'		=>		'application/vnd.rn-realmedia',
		'roff'		=>		'application/x-troff',
		'rtf'		=>		'text/rtf',
		'rtx'		=>		'text/richtext',
		'sgm'		=>		'text/sgml',
		'sgml'		=>		'text/sgml',
		'sh'		=>		'application/x-sh',
		'shar'		=>		'application/x-shar',
		'silo'		=>		'model/mesh',
		'sit'		=>		'application/x-stuffit',
		'skd'		=>		'application/x-koan',
		'skm'		=>		'application/x-koan',
		'skp'		=>		'application/x-koan',
		'skt'		=>		'application/x-koan',
		'smi'		=>		'application/smil',
		'smil'		=>		'application/smil',
		'snd'		=>		'audio/basic',
		'so'		=>		'application/octet-stream',
		'spl'		=>		'application/x-futuresplash',
		'src'		=>		'application/x-wais-source',
		'sv4cpio'	=>		'application/x-sv4cpio',
		'sv4crc'	=>		'application/x-sv4crc',
		'svg'		=>		'image/svg+xml',
		'swf'		=>		'application/x-shockwave-flash',
		't'			=>		'application/x-troff',
		'tar'		=>		'application/x-tar',
		'tcl'		=>		'application/x-tcl',
		'tex'		=>		'application/x-tex',
		'texi'		=>		'application/x-texinfo',
		'texinfo'	=>		'application/x-texinfo',
		'tif'		=>		'image/tiff',
		'tiff'		=>		'image/tiff',
		'tr'		=>		'application/x-troff',
		'tsv'		=>		'text/tab-separated-values',
		'txt'		=>		'text/plain',
		'ustar'		=>		'application/x-ustar',
		'vcd'		=>		'application/x-cdlink',
		'vrml'		=>		'model/vrml',
		'vxml'		=>		'application/voicexml+xml',
		'wav'		=>		'audio/x-wav',
		'wbmp'		=>		'image/vnd.wap.wbmp',
		'wbmxl'		=>		'application/vnd.wap.wbxml',
		'wml'		=>		'text/vnd.wap.wml',
		'wmlc'		=>		'application/vnd.wap.wmlc',
		'wmls'		=>		'text/vnd.wap.wmlscript',
		'wmlsc'		=>		'application/vnd.wap.wmlscriptc',
		'wrl'		=>		'model/vrml',
		'xbm'		=>		'image/x-xbitmap',
		'xht'		=>		'application/xhtml+xml',
		'xhtml'		=>		'application/xhtml+xml',
		'xls'		=>		'application/vnd.ms-excel',
		'xml'		=>		'application/xml',
		'xpm'		=>		'image/x-xpixmap',
		'xsl'		=>		'application/xml',
		'xslt'		=>		'application/xslt+xml',
		'xul'		=>		'application/vnd.mozilla.xul+xml',
		'xwd'		=>		'image/x-xwindowdump',
		'xyz'		=>		'chemical/x-xyz',
		'zip'		=>		'application/zip',
	);
	
	return $mime_type[$extension];
	
}

function page_header($page_title = '')
{
	global $db, $template, $config;
	
	$template->assign(array(
		'PAGE_TITLE'	=>	$page_title,
		'BASE_URL'		=>	'http://' . $_SERVER["SERVER_NAME"],
	));
	
	//assign all the config globals so we can access them
	//in the template throughout the application
	foreach($config as $config_name => $config_value)
	{
		$config_name = strtoupper($config_name);
		$config_row[$config_name] = $config_value;
	}

	$template->assign($config_row);
	
	return true;
}

function page_footer()
{
	global $db, $template;
	page_cleanup();
	
	$template->assign(array(
		//'TOTAL_QUERIES'		=>	$total_queries,
	));
	
	return true;
}

function login_box($redirect = '', $page_title='Login')
{
	global $db, $user, $template, $auth, $phpEx, $base_url, $root_path, $config;

	$template->assign(array(
		'REDIRECT_URL'		=>	$redirect == '' ? $base_url : $redirect,
		'S_LOGIN_PAGE'		=>	true,
	));

	page_header($page_title);

	//output the page
	$template->display($root_path . 'template/login_body.html');

	page_footer();
	
	exit;
}

function is_staff()
{
	global $auth;
	return $auth->user_group['S_STAFF'];
}

function is_manager()
{
	global $auth;
	return $auth->user_group['S_MANAGER'];
}

function is_client()
{
	global $auth;
	return $auth->user_group['S_CLIENT'];
}

function redirect($url)
{
	$url = urldecode($url);
	header("location: $url");
}

/**
*
* @version Version 0.1 / slightly modified for phpBB 3.0.x (using $H$ as hash type identifier)
*
* Portable PHP password hashing framework.
*
* Written by Solar Designer <solar at openwall.com> in 2004-2006 and placed in
* the public domain.
*
* There's absolutely no warranty.
*
* The homepage URL for this framework is:
*
*	http://www.openwall.com/phpass/
*
* Please be sure to update the Version line if you edit this file in any way.
* It is suggested that you leave the main version number intact, but indicate
* your project name (after the slash) and add your own revision information.
*
* Please do not change the "private" password hashing method implemented in
* here, thereby making your hashes incompatible.  However, if you must, please
* change the hash type identifier (the "$P$") to something different.
*
* Obviously, since this code is in the public domain, the above are not
* requirements (there can be none), but merely suggestions.
*
*
* Hash the password
*/
function phpbb_hash($password)
{
	$itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

	$random_state = unique_id();
	$random = '';
	$count = 6;

	if (($fh = @fopen('/dev/urandom', 'rb')))
	{
		$random = fread($fh, $count);
		fclose($fh);
	}

	if (strlen($random) < $count)
	{
		$random = '';

		for ($i = 0; $i < $count; $i += 16)
		{
			$random_state = md5(unique_id() . $random_state);
			$random .= pack('H*', md5($random_state));
		}
		$random = substr($random, 0, $count);
	}

	$hash = _hash_crypt_private($password, _hash_gensalt_private($random, $itoa64), $itoa64);

	if (strlen($hash) == 34)
	{
		return $hash;
	}

	return md5($password);
}

/**
* Check for correct password
*
* @param string $password The password in plain text
* @param string $hash The stored password hash
*
* @return bool Returns true if the password is correct, false if not.
*/
function phpbb_check_hash($password, $hash)
{
	$itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
	if (strlen($hash) == 34)
	{
		return (_hash_crypt_private($password, $hash, $itoa64) === $hash) ? true : false;
	}

	return (md5($password) === $hash) ? true : false;
}

/**
* Generate salt for hash generation
*/
function _hash_gensalt_private($input, &$itoa64, $iteration_count_log2 = 6)
{
	if ($iteration_count_log2 < 4 || $iteration_count_log2 > 31)
	{
		$iteration_count_log2 = 8;
	}

	$output = '$H$';
	$output .= $itoa64[min($iteration_count_log2 + ((PHP_VERSION >= 5) ? 5 : 3), 30)];
	$output .= _hash_encode64($input, 6, $itoa64);

	return $output;
}

/**
* Encode hash
*/
function _hash_encode64($input, $count, &$itoa64)
{
	$output = '';
	$i = 0;

	do
	{
		$value = ord($input[$i++]);
		$output .= $itoa64[$value & 0x3f];

		if ($i < $count)
		{
			$value |= ord($input[$i]) << 8;
		}

		$output .= $itoa64[($value >> 6) & 0x3f];

		if ($i++ >= $count)
		{
			break;
		}

		if ($i < $count)
		{
			$value |= ord($input[$i]) << 16;
		}

		$output .= $itoa64[($value >> 12) & 0x3f];

		if ($i++ >= $count)
		{
			break;
		}

		$output .= $itoa64[($value >> 18) & 0x3f];
	}
	while ($i < $count);

	return $output;
}

/**
* The crypt function/replacement
*/
function _hash_crypt_private($password, $setting, &$itoa64)
{
	$output = '*';

	// Check for correct hash
	if (substr($setting, 0, 3) != '$H$')
	{
		return $output;
	}

	$count_log2 = strpos($itoa64, $setting[3]);

	if ($count_log2 < 7 || $count_log2 > 30)
	{
		return $output;
	}

	$count = 1 << $count_log2;
	$salt = substr($setting, 4, 8);

	if (strlen($salt) != 8)
	{
		return $output;
	}

	/**
	* We're kind of forced to use MD5 here since it's the only
	* cryptographic primitive available in all versions of PHP
	* currently in use.  To implement our own low-level crypto
	* in PHP would result in much worse performance and
	* consequently in lower iteration counts and hashes that are
	* quicker to crack (by non-PHP code).
	*/
	if (PHP_VERSION >= 5)
	{
		$hash = md5($salt . $password, true);
		do
		{
			$hash = md5($hash . $password, true);
		}
		while (--$count);
	}
	else
	{
		$hash = pack('H*', md5($salt . $password));
		do
		{
			$hash = pack('H*', md5($hash . $password));
		}
		while (--$count);
	}

	$output = substr($setting, 0, 12);
	$output .= _hash_encode64($hash, 16, $itoa64);

	return $output;
}

/**
* Hashes an email address to a big integer
*
* @param string $email		Email address
*
* @return string			Big Integer
*/
function phpbb_email_hash($email)
{
	return crc32(strtolower($email)) . strlen($email);
}

/**
* Return unique id
* @param string $extra additional entropy
*/
function unique_id($extra = 'c')
{
	static $dss_seeded = false;
	global $config;

	$val = $config['rand_seed'] . microtime();
	$val = md5($val);
	$config['rand_seed'] = md5($config['rand_seed'] . $val . $extra);

	if ($dss_seeded !== true && ($config['rand_seed_last_update'] < time() - rand(1,10)))
	{
		set_config('rand_seed', $config['rand_seed'], true);
		set_config('rand_seed_last_update', time(), true);
		$dss_seeded = true;
	}

	return substr($val, 4, 16);
}

/**
* Set config value. Creates missing config entry.
*/
function set_config($config_name, $config_value, $is_dynamic = false)
{
	global $db, $config;

	$sql = 'UPDATE ' . CONFIG_TABLE . "
		SET config_value = '" . $db->sql_escape($config_value) . "'
		WHERE config_name = '" . $db->sql_escape($config_name) . "'";

	$db->sql_query($sql);

	/*if (!$db->sql_affectedrows() && !isset($config[$config_name]))
	{
		$sql = 'INSERT INTO ' . CONFIG_TABLE . ' ' . $db->sql_build_array('INSERT', array(
			'config_name'	=> $config_name,
			'config_value'	=> $config_value,
			));
		$db->sql_query($sql);
	}*/

	$config[$config_name] = $config_value;

}

/**
* Error and message handler, call with trigger_error if reqd
*/
function msg_handler($errno, $msg_text, $errfile, $errline)
{
	global $cache, $db, $template, $config, $user;
	global $phpEx, $root_path, $msg_title, $msg_long_text;
	global $base_url;
	
	// Do not display notices if we suppress them via @
	if (error_reporting() == 0 && $errno != E_USER_ERROR && $errno != E_USER_WARNING && $errno != E_USER_NOTICE)
	{
		return;
	}

	// Message handler is stripping text. In case we need it, we are possible to define long text...
	if (isset($msg_long_text) && $msg_long_text && !$msg_text)
	{
		$msg_text = $msg_long_text;
	}

	if (!defined('E_DEPRECATED'))
	{
		define('E_DEPRECATED', 8192);
	}

	switch ($errno)
	{
		case E_NOTICE:
		case E_WARNING:

			// Check the error reporting level and return if the error level does not match
			// If DEBUG is defined the default level is E_ALL
			if (($errno & ((defined('DEBUG')) ? E_ALL : error_reporting())) == 0)
			{
				return;
			}

			if (strpos($errfile, 'cache') === false && strpos($errfile, 'template.') === false)
			{
				// flush the content, else we get a white page if output buffering is on
				if ((int) @ini_get('output_buffering') === 1 || strtolower(@ini_get('output_buffering')) === 'on')
				{
					@ob_flush();
				}

				// Another quick fix for those having gzip compression enabled, but do not flush if the coder wants to catch "something". ;)
				if (!empty($config['gzip_compress']))
				{
					if (@extension_loaded('zlib') && !headers_sent() && !ob_get_level())
					{
						@ob_flush();
					}
				}
			}

			return;

		break;

		case E_USER_ERROR:

			$msg_title = 'General Error';
			$l_return_index = '<a href="javascript:history.go(-1);">Return to Dashboard page</a>';
			$l_notify = '';

			/*if(!$user->data['STAFF'])
			{
				$msg_text = '<p>We are experiencing some difficulties keeping our hampsters going, our engineers have been notified and are working to resolve the issue.</p>';
				notify_staff("My Dashboard: $msg_title", $msg_text, $priority = 5, $html_email = true);
			}*/

			// Do not send 200 OK, but service unavailable on errors
			header('HTTP/1.1 503 Service Unavailable');

			page_cleanup();

			// Try to not call the adm page data...

			echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
			<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="en-gb" xml:lang="en-gb">
			<head>
			<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
			<meta http-equiv="content-style-type" content="text/css" />
			<meta http-equiv="content-language" content="en-gb" />
			<meta http-equiv="imagetoolbar" content="no" />
			<meta name="resource-type" content="document" />
			<meta name="distribution" content="global" />
			<meta name="copyright" content="2009, 2010 Litwicki Media" />
			<meta http-equiv="X-UA-Compatible" content="IE=EmulateIE7" />
			<title>'.$config['dashboard_name'] . ' - ' . $msg_title . '</title>

			<link rel="shortcut icon" type="image/ico" href="$base_url/favicon.ico" />
			<link href="'.$base_url.'/css.php" rel="stylesheet" type="text/css" media="screen, projection" />
			
			</head>

			<body>
				<div id="wrap">
		
					<div id="page-body" class="ui-widget-content ui-helper-reset pad" style="margin-top: 50px;">
						<div style="padding: 20px;">
							<h1>'.$msg_title.'</h1>
							<p>'.$msg_text.'</p>
							<hr><p style="text-align: right;">'.$l_return_index.'</p>
						</div>
					</div>
					
					<div id="page-footer">
						<p style="text-align:center;">Powered by <a href="http://www.mozexa.com?r=litwickimediaclients">Mozexa</a>. Copyright &copy; 2009, 2010. All Rights Reserved.</p>
					</div>
				
				</div>

			</body>
			</html>';

			// On a fatal error (and E_USER_ERROR *is* fatal) we never want other scripts to continue and force an exit here.
			exit;
		break;

		case E_USER_WARNING:
	
		// PHP4 compatibility
		case E_DEPRECATED:
			return true;
		break;
	}

	// If we notice an error not handled here we pass this back to PHP by returning false
	// This may not work for all php versions
	return false;
}

function page_cleanup()
{
	global $db;

	// Close our DB connection.
	if (!empty($db))
	{
		$db->sql_close();
	}
}

function get_backtrace()
{
	global $root_path;

	$output = '<h3>Backtrace</h3><div style="font-family: monospace;">';
	$backtrace = debug_backtrace();
	$path = realpath($root_path);

	foreach ($backtrace as $number => $trace)
	{
		// We skip the first one, because it only shows this file/function
		if ($number == 0)
		{
			continue;
		}

		// Strip the current directory from path
		if (empty($trace['file']))
		{
			$trace['file'] = '';
		}
		else
		{
			$trace['file'] = str_replace(array($path, '\\'), array('', '/'), $trace['file']);
			$trace['file'] = substr($trace['file'], 1);
		}
		$args = array();

		// If include/require/include_once is not called, do not show arguments - they may contain sensible information
		if (!in_array($trace['function'], array('include', 'require', 'include_once')))
		{
			unset($trace['args']);
		}
		else
		{
			// Path...
			if (!empty($trace['args'][0]))
			{
				$argument = htmlspecialchars($trace['args'][0]);
				$argument = str_replace(array($path, '\\'), array('', '/'), $argument);
				$argument = substr($argument, 1);
				$args[] = "'{$argument}'";
			}
		}

		$trace['class'] = (!isset($trace['class'])) ? '' : $trace['class'];
		$trace['type'] = (!isset($trace['type'])) ? '' : $trace['type'];

		$output .= '<br />';
		$output .= '<b>FILE:</b> ' . htmlspecialchars($trace['file']) . '<br />';
		$output .= '<b>LINE:</b> ' . ((!empty($trace['line'])) ? $trace['line'] : '') . '<br />';

		$output .= '<b>CALL:</b> ' . htmlspecialchars($trace['class'] . $trace['type'] . $trace['function']) . '(' . ((sizeof($args)) ? implode(', ', $args) : '') . ')<br />';
	}
	$output .= '</div>';
	return $output;
}

function get_user_val($val_name, $user_id)
{
	global $db;
	
	if(!$user_id)
	{
		return false;
	}
	
	$sql = "SELECT $val_name FROM ".USERS_TABLE." WHERE user_id=$user_id";
	$result = $db->sql_query($sql);
	$row = $db->sql_fetchrow($result);
	
	return $row[$val_name];
}

function parse_phone($phone_number)
{
	global $config;

	$phone_number = preg_replace("/(\d{3})(\d{3})(\d{4})/", $config['phone_format'], $phone_number);
	
	return $phone_number;
	
}

function sanitize_phone($phone_number)
{
	$phone_number = preg_replace("/[^0-9]/", "", $phone_number);
	return $phone_number;
}

function encrypt($text)
{
	return trim(base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $config['encryption_key'], $text, MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND))));
}

function decrypt($text)
{
	return trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $config['encryption_key'], base64_decode($text), MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND)));
} 

?>
