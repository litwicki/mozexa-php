<?php 

/**
 *  @author: jake@litwickimedia.com
 *  @SVN: $Id$
 *  @Copyright 2009,2010 Litwicki Media LLC
 */ 
 
if (!defined('MY_DASHBOARD'))
{
	exit;
}

class file
{
	public function __construct()
	{
		//if we can't write to the files directory, don't bother
        if( !is_writable(FILE_PATH . '/files/') )
		{
			exit;
		}
    }
	
	public function __destruct()
	{
		unset($this);
	}
	
	function add($file_row)
	{
		global $db;

		if(empty($file_row))
		{
			return false;
		}
		
		$sql = 'INSERT INTO '.FILES_TABLE.' ' . $db->sql_build_array('INSERT', $file_row);
		$db->sql_query($sql);
		$file_id = (int) $db->sql_nextid();
		
		return $file_id;
	}
	
	/**
	 *	For a given file_id, append the file_id to the filename
	 *	and physically rename the file for association.
	 */
	function rename($file_id)
	{
		global $db, $root_path;
		
		$sql = "SELECT * FROM ".FILES_TABLE." WHERE file_id=$file_id";
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrow($result);
		
		$filename = $row['unique_filename'];
		$old_file = FILE_PATH . "/files/$filename";
		
		$new_file = FILE_PATH . "/files/" . $file_id . "-" . $filename;
		
		if( rename($old_file, $new_file) )
		{
			$unique_filename = $file_id . "-" . $filename;
			//now update the db record
			$sql = "UPDATE ".FILES_TABLE." SET unique_filename='$unique_filename' WHERE file_id=$file_id";
			$db->sql_query($sql);
			
			return true;
		}
		
		$db->sql_freeresult($result);
		
		return false;
	}
	
	function list_all($user_id, $output = true)
	{
		global $db;
	}

}

?>