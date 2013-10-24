<?php

/****
 *
 *  @author: jake@litwickimedia.com
 *  @SVN: $Id: auth.php 38 2010-06-02 02:52:38Z jake $
 *  @Copyright 2009,2010 Litwicki Media LLC
 *
 ***/ 

if (!defined('MY_DASHBOARD'))
{
	exit;
}

class auth
{
	var $options 				= array();
	var $auth_options			= array();
	var $auth_options_project 	= array();
	var $user_group				= array();
	
	function setup($user_id, $project_id = false)
	{
		global $db, $template;
		
		$user_id = (int) $user_id;
		
		//Build a list of the user permissions from AUTH_options 
		$sql = "SELECT DISTINCT auth_type FROM ".AUTH_OPTIONS_TABLE." ORDER BY auth_type";
		$result = $db->sql_query($sql);
		
		while( $row = $db->sql_fetchrow($result) )
		{
			$auth_type = $row['auth_type'];
			
			$sql_ = "SELECT 
					DISTINCT o.auth_option, au.auth_setting
				FROM 
					".AUTH_OPTIONS_TABLE." o 
					JOIN ".AUTH_USERS_TABLE." au ON au.auth_option_id=o.auth_option_id";
			
			$result_ = $db->sql_query($sql_);
			
			while( $authrow = $db->sql_fetchrow($result_) )
			{
				$this->options[strtoupper($authrow['auth_option'])] = $authrow['auth_setting'] == 1 ? true : false;
			}
			
			$db->sql_freeresult($result_);
		}
		
		$db->sql_freeresult($result);
		
		/**
		 *	A user can belong to a group that has different permissions for a second
		 *	layer of permission throughout the dashboard.
		 *	In this case, we don't care if they ARE NOT part of a group because it's boolean
		 *	whereas individual user permissions have to be set and displayed whether they
		 *	are TRUE or FALSE. Not the case here.
		 */
		
		$sql = "SELECT * FROM ".USER_GROUP_TABLE." ug JOIN ".GROUPS_TABLE." g ON g.group_id=ug.group_id WHERE ug.user_id=$user_id";
		$result = $db->sql_query($sql);
		while( $group_row = $db->sql_fetchrow($result) )
		{
			$this->user_group['S_' . strtoupper($group_row['group_name'])] = true;
		}
		
		$template->assign($this->user_group);
		
		//build smaller list of ONLY permissions $user_id has
		$sql = "SELECT * FROM ".AUTH_USERS_TABLE." u JOIN ".AUTH_OPTIONS_TABLE." o ON o.auth_option_id=u.auth_option_id WHERE u.user_id=$user_id";
		$result = $db->sql_query($sql);
		while( $row = $db->sql_fetchrow($result) )
		{
			$this->auth_options[strtoupper($row['auth_option'])] = $row['auth_option_id'];
			$template->assign(array(
				strtoupper($row['auth_option'])		=>	$row['auth_setting'] == 1 ? true : false,
			));
		}
		
		$db->sql_freeresult($result);
		
		$sql = "SELECT project_id FROM ".PROJECTS_TABLE." WHERE status = 1";
		$result = $db->sql_query($sql);
		
		while( $row = $db->sql_fetchrow($result) )
		{
			$project_id = (int) $row['project_id'];
			
			/**
			 *	Overwrite global permissions with project permissions only if we're in THAT project. 
			 *	If a permission is not found in AUTH_PROJECT_USERS_TABLE then ignore it and use the global permission!
			 *	
			 *	Used in: dashboard.php => get_projectlist(), projects.php
			 */

			$sql_ = "SELECT * FROM ".AUTH_PROJECT_USERS_TABLE." u JOIN ".AUTH_OPTIONS_TABLE." o ON o.auth_option_id=u.auth_option_id WHERE u.user_id=$user_id AND u.project_id=$project_id";
			$result_ = $db->sql_query($sql_);

			while( $row_ = $db->sql_fetchrow($result_) )
			{
				$this->auth_options_project[$project_id][strtoupper($row_['auth_option'])] = $row_['auth_setting'];
			}
			
			$db->sql_freeresult($result_);
		}
		
		$db->sql_freeresult($result);
		
		return true;
	}
	
	/**
	 *	Send true/false toggles for each group by group_id
	 *	@param: $group_array = array( $group_id => true/false, );
	 */
	 
	function user_groups($user_id, $group_array)
	{
		global $db;
		
		foreach($group_array as $group_id => $auth_setting)
		{
			if($auth_setting)
			{
				$db->sql_query("INSERT INTO ".USER_GROUP_TABLE." (user_id, group_id) VALUES ($user_id, $group_id)");
			}
			else
			{
				$db->sql_query("DELETE FROM ".USER_GROUP_TABLE." WHERE user_id=$user_id AND group_id=$group_id");
			}
		}
		
		return true;
		
	}
	
	function remove_permission($user_id, $auth_option_id, $project_id = false)
	{
		global $db;
		
		$auth_table = ($project_id ? AUTH_PROJECT_USERS_TABLE : AUTH_USERS_TABLE);
		
		$sql = "DELETE FROM ". $auth_table ." WHERE user_id=$user_id AND auth_option_id=$auth_option_id" . ($project_id ? " AND project_id=$project_id" : "");
		$db->sql_query($sql);
		
		return true;
	}
	
	function edit_permission($user_id, $auth_option_id, $auth_setting, $project_id = false)
	{
		global $db;
		
		$sql = "UPDATE ".AUTH_PROJECT_USERS_TABLE." SET auth_setting=$auth_setting WHERE auth_option_id=$auth_option_id AND user_id=$user_id" . ($project_id ? " AND project_id=$project_id" : "");
		$db->sql_query($sql);
		
		return true;
	}
	
	function add_permission($auth_row)
	{
		global $db;
		
		$project_id = (int) $auth_row['project_id'];
		
		$auth_table = ($project_id ? AUTH_PROJECT_USERS_TABLE : AUTH_USERS_TABLE);

		$sql = 'INSERT INTO '. $auth_table .' ' . $db->sql_build_array('INSERT', $auth_row);
		$db->sql_query($sql);
		return true;
	}
	
	function get_permission($user_id, $auth_option_id, $project_id = false)
	{
		global $db;
		
		$auth_table = ($project_id ? AUTH_PROJECT_USERS_TABLE : AUTH_USERS_TABLE);
		
		$sql = "SELECT * FROM ". $auth_table . " WHERE user_id=$user_id AND auth_option_id=$auth_option_id" . ($project_id ? " AND project_id=$project_id" : "");
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);
		
		return $row;
		
	}
	
	/**
	 *	@purpose: 	Display all permissions for a particular user and if specified, a specific project.
	 *				Users can have completely seperate permissions for individual projects
	 *				that supersede their global permissions.
	 */	
	
	function mypermissions($user_id, $project_id = false)
	{
		global $db, $template;
		
		$user_id = (int) $user_id;

		//Build a list of the user permissions from AUTH_options
		
		if($project_id)
		{
			$sql = "SELECT DISTINCT auth_type FROM ".AUTH_OPTIONS_TABLE." WHERE auth_type IN('task','message','proposal') ORDER BY auth_type";
		}
		else
		{
			$sql = "SELECT DISTINCT auth_type FROM ".AUTH_OPTIONS_TABLE." ORDER BY auth_type";
		}
		
		$result = $db->sql_query($sql);
		
		while( $row = $db->sql_fetchrow($result) )
		{
			$auth_type = $row['auth_type'];

			if($project_id)
			{
				$sql_ = "SELECT 
						*, (SELECT COUNT(*) FROM ".AUTH_PROJECT_USERS_TABLE." WHERE auth_option_id=o.auth_option_id AND project_id=$project_id AND user_id=$user_id) AS auth_setting 
					FROM 
						".AUTH_OPTIONS_TABLE." o
					WHERE 
						o.auth_type='$auth_type' 
					ORDER BY 
						o.auth_option";
			}
			else
			{
				$sql_ = "SELECT 
						*, (SELECT COUNT(*) FROM ".AUTH_USERS_TABLE." WHERE auth_option_id=o.auth_option_id AND user_id=$user_id) AS auth_setting 
					FROM 
						".AUTH_OPTIONS_TABLE." o
					WHERE 
						o.auth_type='$auth_type' 
					ORDER BY 
						o.auth_option";
			}

			$result_ = $db->sql_query($sql_);
			while( $authrow = $db->sql_fetchrow($result_) )
			{
				$authrow_type = 'authrow_' . $auth_type;
				$authrow_type_row = array(
					'AUTH_SETTING'			=>	$authrow['auth_setting'] == 1 ? true : false,
					'AUTH_OPTION_ID'		=>	$authrow['auth_option_id'],
					'AUTH_OPTION'			=>	$authrow['auth_option'],
					'AUTH_ICO'				=>	$authrow['ico'],
					'AUTH_OPTION_CLEAN'		=>	ucwords(str_replace("_"," ",str_replace("u_","",$authrow['auth_option']))),
				);
				
				$authrow_array[] = $authrow_type_row;
				unset($authrow_type_row);

			}

			$template->assign($authrow_type, $authrow_array);
			unset($authrow_array);
		}
		
		return true;
		
	}
	
	function auth_option_id($auth_option)
	{
		global $db;
		
		$sql = "SELECT auth_option_id FROM ".AUTH_OPTIONS_TABLE." WHERE auth_option='$auth_option'";
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrow($result);
		
		$auth_option_id = (int) $row['auth_option_id'];
		
		$db->sql_freeresult($result);
		
		return $auth_option_id;
	}
	
}

?>