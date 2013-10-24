<?php

/**
 *  @author: jake@litwickimedia.com
 *  @SVN: $Id: dashboard.php 49 2010-06-08 14:01:42Z jake $
 *  @Copyright 2009,2010 Litwicki Media LLC
 */ 
 
if (!defined('MY_DASHBOARD'))
{
	exit;
}

class dashboard
{
    public function __construct()
	{
        $this->timestamp = time();
		$this->closed_status = 2;
		$this->open_status = 1;
		$this->approve_status = 2;
		$this->delete_status = 0;
    }
	
	public function __destruct()
	{
		unset($this);
	}
	
	//setup some global dashboard settings
	function setup($user_id)
	{
		global $db, $auth, $template, $config;

		if(!$user_id)
		{
			return false;
		}
		
		//build growl alerts for dashboard
		$sql = "SELECT * FROM ".LOG_TABLE." WHERE owner_id=$user_id AND growl=1 ORDER BY log_date DESC";
		$result = $db->sql_query($sql);
		$growls = $db->sql_affectedrows($result);
		
		if( $growls > 0 )
		{
			$template->assign(array(
				'SHOW_DASHBOARD_ALERTS'	=>	true,
			));
			
			while( $row = $db->sql_fetchrow($result) )
			{
				$alert_text = date('h:i A - m/d/y', $row['log_date']) . '<br />' . $row['log_details'];
				
				$alert = array(
					'ALERT_TEXT'	=>	litwicki_decode($alert_text),
					'ALERT_ID'		=>	$row['log_id'],
				);
				
				$alertrow_array[] = $alert;
				unset($alert);
			}
			
			$db->sql_freeresult($result);
			
			$template->assign('alertrow', $alertrow_array);
		}

		//spit out $user details
		$user_row = $this->user_details($user_id);
		foreach($user_row AS $key => $value)
		{
			if( $key == 'user_gender' )
			{
				$key = 'USER_GENDER_ICON';
				$value = ($value == 'f' ? 'user-female' : 'user-male');
			}
			
			$template->assign(array(
				strtoupper($key)	=>	$value,
			));
		}
		
		/**
		 *	Staff specific 'stuff'
		 */
		
		//buidl list of service rates
		$sql = "SELECT * FROM ".RATES_TABLE." WHERE status=1 ORDER BY description";
		$result = $db->sql_query($sql);
		while( $row = $db->sql_fetchrow($result) )
		{
			$rates[] = array_change_key_case($row, CASE_UPPER);
		}
		
		$template->assign('raterow',$rates);

		$template->assign(array(
			'SHOW_SERVICE_RATES'	=>	count($rates) > 0 ? true : false,
		));

		return true;
	}

	function remove_growl($log_id)
	{
		global $db;
		$sql = "UPDATE ".LOG_TABLE." SET growl=0 WHERE log_id=$log_id";
		
		if( $db->sql_query($sql) )
		{
			return true;
		}
		
		return false;
	}
	
	function add_client($client_row)
	{
		global $db, $user;
		
		if(!$client_row['date_added'])
		{
			$client_row['date_added'] = time();
		}
		
		$sql = 'INSERT INTO '.CLIENTS_TABLE.' ' . $db->sql_build_array('INSERT', $client_row);
		$result = $db->sql_query($sql);
		$client_id = $db->sql_nextid();
		$db->sql_freeresult($result);
		return $client_id;
		
	}
	
	function modify_client($client_id, $client_row)
	{
		global $db, $user;
		
		$sql = 'UPDATE ' . CLIENTS_TABLE . ' SET ' . $db->sql_build_array('UPDATE', $client_row) . ' WHERE client_id = ' . (int) $client_id;
		$db->sql_query($sql);
		
		return true;
		
	}
	
	/**
	 *	Process array of user_ids for a specific client_id
	 *	and add/remove as specified by the selected user_ids.
	 */
	
	function client_users($selected_users, $client_id)
	{
		global $db;
		
		/*if( empty($selected_users) || !$client_id )
		{
			return false;
		}*/
		
		//get a list of all currently associated users
		$sql = "SELECT user_id FROM ".CLIENT_USERS_TABLE." WHERE client_id=$client_id";
		$result = $db->sql_query($sql);
		while($row = $db->sql_fetchrow($result) )
		{
			$current_users = $row;
		}
		
		foreach($selected_users as $user_id)
		{	
			//is this user already associated?
			$sql = "SELECT * FROM ".CLIENT_USERS_TABLE." WHERE user_id=$user_id AND client_id=$client_id";
			$result = $db->sql_query($sql);
			$user_count = $db->sql_affectedrows($result);
			
			if($user_count == 0)
			{
				$sql = "INSERT INTO ".CLIENT_USERS_TABLE." (user_id, client_id) VALUES ($user_id, $client_id)";
				$db->sql_query($sql);
			}
			else
			{
				//user is already associated, but are they part of selected_users()?
				if (!in_array($user_id, $selected_users)) 
				{
					//user is not selected, which means we don't want them
					//to be associated anymore
					$sql = "DELETE FROM ".CLIENT_USERS_TABLE." WHERE user_id=$user_id AND client_id=$client_id";
					$db->sql_query($sql);
				}
			}
		}
		
		$db->sql_freeresult($result);
		
		return true;
		
	}

	function get_project_detail($project_id, $output = true)
	{
		global $db, $user, $auth, $template, $config;

		if( !$project_id )
		{
			return false;
		}
		
		$this_user_id = (int) $user->data['user_id'];
		
		$sql = "SELECT
					p.project_id, p.project_name, p.project_description, p.client_id, p.user_id, p.status as project_status, p.status_date as project_status_date, p.start_date as start_date, p.contract_required, 
					c.company, c.zip, c.state, c.city, c.phone, c.address, c.status as client_status
				FROM 
					".PROJECTS_TABLE." p
					JOIN ".CLIENTS_TABLE." c ON c.client_id=p.client_id
				WHERE
					p.project_id=$project_id";
				
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrow($result);

		$db->sql_freeresult($result);
		
		$project_id = (int) $row['project_id'];

		//for every task linked to this project, calculate total hours
		$sql = "SELECT task_id FROM ".TASKS_TABLE." WHERE project_id=$project_id";
		$result = $db->sql_query($sql);
		while( $timerow = $db->sql_fetchrow($result) )
		{
			$task_minutes = $this->task_minutes($timerow['task_id']);
			$total_task_minutes += $task_minutes;
			unset($task_minutes);
		}
		
		$db->sql_freeresult($result);
		
		$project_row = array(
			'PROJECT_NAME'			=>	litwicki_decode($row['project_name']),
			'PROJECT_DESCRIPTION'	=>	litwicki_decode($row['project_description']),
			'START_DATE'			=>	date($config['date_long'], $row['start_date']),
			'PROJECT_ID'			=>	$row['project_id'],
			'CLIENT_ID'				=>	$row['client_id'],
			'OWNER_ID'				=>	$row['user_id'],
			'OWNER_NAME'			=>	get_user_val("user_realname", $row['user_id']),
			'PROJECT_STATUS'		=>	$row['project_status'],
			'PROJECT_STATUS_DATE'	=>	date($config['date_long'], $row['project_status_date']),
			'REQUEST_ID'			=>	(int) $row['request_id'],
			'PROJECT_HOURS'			=>	round( ($total_task_minutes / 60) , 2),
			'START_DATE'			=>	date($config['date_short'], $row['start_date']),
			'CONTRACT_REQUIRED'		=>	$row['contract_required'] == 1 ? true : false,
		);

		if($output)
		{
			$template->assign($project_row);

			//get all users associated to this client_id
			$client_id = (int) $row['client_id'];
			$this->get_client_detail($client_id);
		}

		return $row;
		
	}
	// end $project->get_detail();
	
	function get_client_detail($client_id, $output = true)
	{
		global $db, $template, $config;
		
		$sql = "SELECT *, (SELECT user_id as super_user_id FROM ".CLIENT_USERS_TABLE." WHERE client_id=$client_id AND super=1) as super_user_id FROM ".CLIENTS_TABLE." WHERE client_id=$client_id";

		$result = $db->sql_query($sql);
		$client_row = $db->sql_fetchrow($result);
		
		$db->sql_freeresult($result);
		
		$client_url = $client_row['website'];
		
		$client_link = ($client_url == '' ? '' : '<a class="external" href="' . $client_url . '">' . $client_url . '</a>');
		
		if($output)
		{
			$template->assign(array(
				'CLIENT_ID'		=>	$client_row['client_id'],
				'COMPANY'		=>	$client_row['company'],
				'ADDRESS'		=>	$client_row['address'],
				'PHONE'			=>	parse_phone($client_row['phone']),
				'CITY'			=>	$client_row['city'],
				'STATE'			=>	$client_row['state'],
				'ZIPCODE'		=>	$client_row['zip'],
				'JOIN_DATE'		=>	date($config['date_long'], $client_row['date_added']),
				'FULL_ADDRESS'	=>	$client_row['address'] . '<br />' . $client_row['city'] . ' ' . $client_row['state'] . ', ' . $client_row['zip'],
				'SUPER_USER_ID'	=>	$client_row['super_user_id'],
				'WEBSITE'		=>	$client_link,
			));
		}
		
		//get all client_users
		$sql = "SELECT *
				FROM
					".CLIENT_USERS_TABLE." cu
					JOIN ".CLIENTS_TABLE." c ON c.client_id = cu.client_id
					JOIN ".USERS_TABLE." u ON u.user_id=cu.user_id
				WHERE
					c.client_id=$client_id
				ORDER BY
					u.user_lastname DESC, u.user_firstname";
			
		$result = $db->sql_query($sql);
		while( $row = $db->sql_fetchrow($result) )
		{
			$row['GENDER_ICO'] = ($row['user_gender'] == 'f' ? 'user-female' : 'user-male');
			$row['USER_PHONE'] = parse_phone($row['user_phone']);
			//$row['USER_PHONE'] = preg_replace("/(\d{3})(\d{3})(\d{4})/","($1) $2-$3",$row['user_phone']);
			$clientuser_array[] = array_change_key_case($row, CASE_UPPER);
			unset($row);
		}
		
		$db->sql_freeresult($result);
		
		if($output)
		{
			$template->assign('clientuser',$clientuser_array);
		}

		return $client_row;
		
	}
	
	function project_owner($project_id)
	{
		global $db;
		
		$sql = "SELECT user_id FROM ".PROJECTS_TABLE." WHERE project_id=$project_id";
		$result = $db->sql_query($sql);
		$row = $db->sql_query($sql);
		
		$owner_id = (int) $row['user_id'];
		
		$db->sql_freeresult($result);
		
		return $owner_id;
	}
	//end project_owner();
	
	function total_project_minutes($project_id)
	{
		global $db;
		
		$project_id = (int) $project_id;
		
		$sql = "SELECT task_id FROM ".TASKS_TABLE." WHERE project_id=$project_id";
		$result = $db->sql_query($sql);
		
		while( $row = $db->sql_fetchrow($result) )
		{
			$task_minutes = $this->task_minutes($row['task_id']);
			$total_minutes += $task_minutes;
			unset($task_minutes);
		}
		
		$db->sql_freeresult($result);
		
		return $total_minutes;
		
	}
	//end total_project_minutes();
	
	/*
	*	Add a new project to Litwicki Media
	*	If client_user_id is specified, also assign them to the project
	*	This is a fallback for when a project is created internally or otherwise
	*	not from the proposal request form.
	*/
	function add_project($project_row)
	{
		global $db, $admin_user_id, $base_url;

		//make sure we have a default status_date
		
		if(!$project_row['status_date'])
		{
			$project_row['status_date'] = time();
		}
		
		/**
		 *	if we're creating a new project for a request, let's default a plain description
		 *	so it doesn't look broken when we first view it.
		 */
		if( $project_row['status'] == 3 && $project_row['project_description'] == "" )
		{
			$project_row['project_description'] = "[PROJECT DESCRIPTION]";
		}
		
		$client_user_id = (int) $project_row["client_user_id"];

		$sql = 'INSERT INTO '.PROJECTS_TABLE.' ' . $db->sql_build_array('INSERT', $project_row);
		$db->sql_query($sql);
		$project_id = $db->sql_nextid();

		//always assign the admin to the project by default
		//$this->assign('project', $admin_user_id, $project_id);

		if($client_user_id)
		{
			//assign client to the project
			$this->assign($client_user_id, $project_id, $send_email = false, $growl = true);
		}
		
		$user_realname = $user->data['user_realname'];
		$loglink = "$base_url/projects.php?id=$project_id";
		$project_link = '<a title="'.$project_name.'" href="'.$loglink.'">'.$project_name.'</a>';
		$log_message = "$user_realname created a new project $projectlink";
	
		$this->dashboard_log($project_row['user_id'], $client_user_id, $this->timestamp, $log_message);

		return $project_id;
	}
	// end $project->add()
	
	function modify_project($project_id, $project_row)
	{
		global $db;
		
		//make sure we have a default status_date
		
		if(!$project_row['status_date'])
		{
			$project_row['status_date'] = time();
		}
		
		$sql = 'UPDATE ' . PROJECTS_TABLE . ' SET ' . $db->sql_build_array('UPDATE', $project_row) . ' WHERE project_id = ' . (int) $project_id;
		$db->sql_query($sql);
		
		//$project_row = $this->get_project_detail($project_id, false);
		return true;
	}

	//quick user details fetch
	//same as user_detail in /includes/user.php 
	function user_details($user_id)
	{
		global $db, $config;
		
		if(!$user_id)
		{
			return false;
		}
		
		$sql = "SELECT * FROM ".USERS_TABLE." u LEFT JOIN ".SESSIONS_TABLE." s ON s.session_user_id=u.user_id WHERE u.user_id=$user_id";
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrow($result);
		
		$row['session_last_visit'] = date($config['date_long'], $row['session_last_visit']);
		
		$db->sql_freeresult($result);
		
		return $row;
		
	}
	
	function task_assigned($user_id, $task_id)
	{
		global $db;
		
		$user_id = (int) $user_id;
		$task_id = (int) $task_id;

		//first check if user is part of this client group
		$sql = "SELECT 
				cu.user_id 
			FROM 
				".CLIENT_USERS_TABLE." cu
				JOIN ".CLIENTS_TABLE." c ON c.client_id=cu.client_id
				JOIN ".PROJECTS_TABLE." p ON p.client_id=c.client_id
				JOIN ".TASKS_TABLE." t ON t.project_id=p.project_id
			WHERE
				t.task_id=$task_id 
				AND cu.user_id=$user_id";
				
		$result = $db->sql_query($sql);
		
		while( $row = $db->sql_fetchrow($result) )
		{
			if ($user_id == $row['user_id'])
			{
				return true;
			}
		}
		
		$db->sql_freeresult($result);
		
		
		//not a client user, are they a staff member assigned?
		$sql = "SELECT 
					tu.user_id 
				FROM 
					".TASK_USERS_TABLE." tu
					JOIN ".TASKS_TABLE." t ON t.task_id=tu.task_id
				WHERE
					t.task_id=$task_id 
					AND tu.user_id=$user_id";
					
		while( $row = $db->sql_fetchrow($result) )
		{
			if ($user_id == $row['user_id'])
			{
				return true;
			}
		}
		
		$db->sql_freeresult($result);
		
		return false;
		
	}
	
	function get_task_detail($task_id, $output = true) 
	{
		global $db, $template, $config;

		$sql = "SELECT 
					t.*, p.project_name, c.company, p.user_id, p.project_id, p.client_id 
				FROM 
					".TASKS_TABLE." t
					JOIN ".PROJECTS_TABLE." p ON p.project_id=t.project_id 
					JOIN ".CLIENTS_TABLE." c ON c.client_id=p.client_id
				WHERE 
					t.task_id=$task_id";

		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		$task_hours = round($this->task_minutes($row['task_id']) / 60, 2);
		$task_hours_label = $task_hours > 0 ? "There are $task_hours hours logged for this task." : "There are no hours logged for this task.";
		$row['task_hours'] = $task_hours_label;
		
		if($output)
		{
			$template->assign(array(
				'COMPANY_NAME'			=>	$row['company_name'],
				'DATE_ADDED'			=>	date($config['date_long'], $row['date_added']),
				'DUE_DATE'				=>	date($config['date_long'], $row['due_date']),
				'PROJECT_ID'			=>	$row['project_id'],
				'TASK_ID'				=>	$row['task_id'],
				'TASK_HOURS'			=>	$task_hours_label,
				'TASK_DESCRIPTION'		=>	litwicki_decode($row['task_description']),
				'TASK_NAME'				=>	litwicki_decode($row['task_name']),
				'PROJECT_NAME'			=>	$row['project_name'],
			));

			//get all the logged data for this task
			$sql = "SELECT * FROM ".TASK_TIMELOG_TABLE." WHERE task_id=$task_id AND status=1 ORDER BY work_date";
			$result = $db->sql_query($sql);
			$timelogs = $db->sql_affectedrows($result);
			
			if($timelogs > 0)
			{
				while( $timerow = $db->sql_fetchrow($result) )
				{
					$total_mins += $timerow['minutes'];

					$timerow['WORKER_NAME']			=	get_user_val('user_realname', $timerow['user_id']);
					$timerow['WORK_DATE']			=	date($config['date_short'], $timerow['work_date']);
					$timerow['DATE_LOGGED']			=	date($config['date_long'], $timerow['date_added']);
					$timerow['WORK_HOURS']			=	round($timerow['minutes'] / 60, 2);
					$timerow['WORK_DESCRIPTION']	=	litwicki_decode($timerow['work_description']);
					
					$timelogrow_array[] = array_change_key_case($timerow, CASE_UPPER);
					unset($timerow);
					
				}
				
				$template->assign('timelogrow', $timelogrow_array);
				unset($timelogrow_array);
				
			}
			
			$template->assign(array(
				'SHOW_TIMELOG'		=>	(($timelogs > 0) ? true : false),
				'TOTAL_HOURS'		=>	round($total_mins / 60, 2),
			));
			
			//get users assigned to task
			$task_users = $this->get_assigned_users('task', $task_id);

			$db->sql_freeresult($result);
		}
		
		return $row;

	}
	// end $task->get_detail()

	function task_minutes($task_id)
	{
		global $db;
		
		$sql = "SELECT SUM(minutes) AS total_minutes FROM ".TASK_TIMELOG_TABLE." WHERE task_id=$task_id AND status=1";
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrow($result);
		
		$total_minutes = (int) $row['total_minutes'];
		
		$db->sql_freeresult($result);
		
		return $total_minutes;
		
	}

	function add_task($task_row)
	{
		global $db;
		
		//add the task
		$sql = 'INSERT INTO '.TASKS_TABLE.' ' . $db->sql_build_array('INSERT', $task_row);
		$result = $db->sql_query($sql);
		$task_id = $db->sql_nextid();
		$db->sql_freeresult($result);
		
		//get project user_id for owner_id to log
		$project_id = $task_row['project_id'];
		$owner_id = $this->project_owner($project_id);
		
		unset($project_row);

		$user_row = $this->user_details($task_row['user_id']);
		$user_realname = $user_row['user_realname'];
		
		$tasklink = $base_url . "/tasks.php?id=" . $task_id;
		$loglink = '<a href="'.$tasklink.'">'.$task_name.'</a>';
		$log_message = "$user_realname created a new task: $loglink.";
		
		$this->dashboard_log($task_row['user_id'], $owner_id, $this->timestamp, $log_message);
		
		return $task_id;

	}
	// end $task->add()
	
	function add_task_timelog($timelog_row, $send_email=false)
	{
		global $db, $config;

		//add the task
		$sql = 'INSERT INTO '.TASK_TIMELOG_TABLE.' ' . $db->sql_build_array('INSERT', $timelog_row);
		$result = $db->sql_query($sql);
		$task_log_id = $db->sql_nextid();
		
		$task_id = $timelog_row['task_id'];
		
		$taskrow = $this->get_task_detail($task_id, false);
		$taskname = $taskrow['task_name'];
		unset($taskrow);
		
		$subject = $config['dashboard_name'] . " - Time Logged for $taskname";
		$message = "A staff member has logged time for a task under one of your projects. You can view the details here: $base_url/tasks.php?id=$task_id";
		$this->dashboard_email($subject, $message, $taskrow['client_id'], true);

		$db->sql_freeresult($result);
		
		return $db->sql_nextid();
		
	}
	// end add_task_timelog()
	
	function modify_task($task_id, $task_row)
	{
		global $db;

		$sql = 'UPDATE ' . TASKS_TABLE . ' SET ' . $db->sql_build_array('UPDATE', $task_row) . ' WHERE task_id = ' . (int) $task_id;
		$db->sql_query($sql);
		
		return true;
	}
	// end $task->modify()
	
	function approve_task($task_approve_row)
	{
		global $db;
		
		$sql = 'INSERT INTO '.APPROVED_TASKS_TABLE.' ' . $db->sql_build_array('INSERT', $task_approve_row);
		$result = $db->sql_query($sql);
		$approved_task_id = $db->sql_nextid();
		
		$db->sql_freeresult($result);
		
		return true;
		
	}
	//end approve_task()
	
	function open_task($task_row)
	{
		global $db;
		
		$task_id 			= $task_row['task_id'];
		$approval_notes 	= $task_row['approval_notes'];
		$email_subject 		= $task_row['email_subject'];
		$email_message 		= $task_row['email_message'];
		$email_priority 	= $task_row['email_priority'];
		
		$this->change_status('task', $task_id, $this->open_status);
	
		//delete the approved record
		$sql = "DELETE FROM ".APPROVED_TASKS_TABLE." WHERE task_id=$task_id";
		$result = $db->sql_query($sql);
		
		$db->sql_freeresult($result);

		//email staff that the completed work was DECLINED by the client, huge deal!
		notify_staff($email_subject, $email_message, $html_email = true, $email_priority);
		
		return true;
		
	}
	
	//Get all the details of a message and return it as an array to be parsed in the particular code page.
	function get_message_detail($message_id, $output = true)
	{
		global $db, $template, $user, $config;
		
		$user_id = (int) $user->data['user_id'];
		
		//let's set a flag if this is marked read or not.
		$sql = "SELECT * FROM ".READ_MESSAGES_TABLE." WHERE user_id=$user_id AND message_id=$message_id";
		$result = $db->sql_query($sql);
		$message_read = $db->sql_affectedrows($result);
		
		unset($sql);
		unset($result);

		$sql = "SELECT
				  m.*, 
				  u.user_realname, u.user_email, u.user_phone,
				  pm.project_id as project_id, 
				  p.project_name, 
				  um.user_id AS recipient_user_id 
				FROM
				  ".MESSAGES_TABLE." m
				  LEFT JOIN ".USERS_TABLE." u ON u.user_id=m.user_id 
				  LEFT JOIN ".USER_MESSAGES_TABLE." um ON um.message_id=m.message_id
				  LEFT JOIN ".PROJECT_MESSAGES_TABLE." pm ON pm.message_id=m.message_id 
				  LEFT JOIN ".PROJECTS_TABLE." p ON p.project_id=pm.project_id 
				WHERE
				  m.message_id=$message_id";

		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrow($result);
		
		//$user_phone = preg_replace("/(\d{3})(\d{3})(\d{4})/","($1) $2-$3",$row['user_phone']);
		$user_phone = parse_phone($row['user_phone']);
		$row['user_phone'] = $user_phone;
		
		$user_realname = $row['user_realname'];
		$user_email = $row['user_email'];

		//get attachments
		$attachrow = $this->get_attachments($message_id, 1, $output);
		$attachment_count = count($attachrow);
		
		//get all replies for this message
		$reply_count = $this->get_message_replies($message_id, $output);
		$reply_count_message = "There " . (($reply_count == 1) ? "is 1 reply" : "are $reply_count replies") . ".";
		
		$row['reply_count_message'] = $reply_count_message;
		$row['reply_count'] = $reply_count;
		$row['show_replies'] = $reply_count;
		
		//clean up the strings in case we don't output them with $template()
		$row['message'] = litwicki_decode($row['message']);
		$row['subject'] = litwicki_decode($row['subject']);
		
		$recipient_name = ucwords(get_user_val('user_realname', $row['recipient_user_id']));
		$row['recipient_name'] = $recipient_name;
		
		if($output)
		{
			$user_avatar = get_user_val('user_avatar', $row['user_id']);
			$default_avatar = (get_user_val('user_gender', $row['user_id']) == 'f' ? 'female.jpg' : 'male.jpg');
			$author_avatar =  $user_avatar == '' ? $default_avatar : $user_avatar;
		
			$template->assign(array(
				'CLIENT_NAME'			=>	$user_realname,
				'DATE_ADDED'			=>	date($config['date_long'], $row['date_added']),
				'MESSAGE_ID'			=>	$row['message_id'],
				'MESSAGE'				=>	$row['message'],
				'SUBJECT'				=>	$row['subject'],
				'S_MARKED_READ'			=>	$message_read,
				'SHOW_REPLIES'			=>	(($reply_count) ? true : false),
				'REPLY_COUNT'			=>	$reply_count,
				'REPLY_COUNT_MESSAGE'	=>	$reply_count_message,
				'ATTACHMENT_COUNT'		=>	$attachment_count,
				'SHOW_ATTACHMENTS'		=>	$attachment_count > 0 ? true : false,
				'AUTHOR'				=>	get_user_val('user_realname', $row['user_id']),
				'AUTHOR_USER_ID'		=>	$row['user_id'],
				'AUTHOR_AVATAR'			=>	$author_avatar,
				
				'PROJECT_ID'			=>	$row['project_id'],
				'PROJECT_NAME'			=>	$row['project_name'],
				
				'RECIPIENT_USER_ID'		=>	$row['recipient_user_id'],
				'RECIPIENT_NAME'		=>	$recipient_name,
				
			));
		}
		
		$db->sql_freeresult($result);
		return $row;

	}
	// end get_message_detail()
	
	function add_message($message_row, $attachments = false)
	{
		global $db, $user;
		
		//overwrite date_added to guarantee it is assigned
		$message_row['date_added'] = time();

		//add the message
		$sql = 'INSERT INTO '.MESSAGES_TABLE.' ' . $db->sql_build_array('INSERT', $message_row);
		$db->sql_query($sql);
		$message_id = $db->sql_nextid();

		//if the attachments array has files..
		if( !empty($attachments) )
		{
			$attachcount = count($attachments);
			for($i = 0; $i <= $imgnum; $i++ )
			{
				$tmpname = $attachments['tmp_name'][$i];
				$filename = $attachments['name'][$i];
				$filename = str_replace(" ","_",$filename);
				
				$file_ext = preg_replace("/.*\.(.*?)/","$1",$filename);
				$unique_filename = $message_id . "__" . time() . "." . $file_ext;
				$unique_filename = str_replace(" ","_",$unique_filename);
				
				//build the attachment_row
				$file_row = array(
					'filename'			=>	$filename,
					'unique_filename'	=>	$unique_filename,
					'filesize'			=>	filesize($tmpname),
					'file_ext'			=>	$file_ext,
					'date_added'		=>	time(),
					'user_id'			=>	$message_row['user_id'],
				);
				
				$this->add_attachment($message_id, $file_row);
				unset($file_row);
			}
		}

		return $message_id;
	}
	//end add_message()
	
	function add_user_message($user_id, $message_id, $log = true)
	{
		global $db, $user;
		
		$author_user_id = (int) $user->data['user_id'];
		
		if(!$user_id || !$message_id)
		{
			return false;
		}
		
		$row = array(
			'user_id'		=>	(int) $user_id,
			'message_id'	=>	(int) $message_id,
		);
		
		$sql = 'INSERT INTO '.USER_MESSAGES_TABLE.' ' . $db->sql_build_array('INSERT', $row);
		$db->sql_query($sql);
		
		/**
		 *	Log this if it's a new message but keep this flag in case this is a reply
		 *	so we can still log the reply message.
		 */
		
		if($log)
		{
			$user_row = $this->user_details($author_user_id);
			$user_realname = $user_row['user_realname'];
		
			$message_row = $this->get_message_detail($message_id, false);
			$subject = litwicki_decode($message_row['subject']);
		
			$logurl = $base_url . "/messages.php?id=" . $message_id;
			$loglink = '<a href="'.$logurl.'">'.$subject.'</a>';
			
			$log_message = "$user_realname sent you a new message: $loglink.";
			
			$this->dashboard_log($author_user_id, $user_id, $this->timestamp, $log_message);
		}
		
		return true;
			
	}
	
	function add_project_message($project_id, $message_id, $log = true)
	{
		global $db, $user;
		
		$author_user_id = (int) $user->data['user_id'];
		
		if(!$message_id || !$project_id)
		{
			return false;
		}
		
		$row = array(
			'message_id'	=>	(int) $message_id,
			'project_id'	=>	(int) $project_id,
		);
		
		$sql = 'INSERT INTO '.PROJECT_MESSAGES_TABLE.' ' . $db->sql_build_array('INSERT', $row);
		$db->sql_query($sql);
		
		if($log)
		{
			$user_row = $this->user_details($author_user_id);
			$user_realname = $user_row['user_realname'];
		
			$logurl = $base_url . "/messages.php?id=" . $message_id;
			$message_row = $this->get_message_detail($message_id);
			$subject = $message_row['subject'];
			$message_link = '<a href="'.$logurl.'">'.$subject.'</a>';
			
			$project_row = $this->get_project_detail($project_id);
			$project_name = litwicki_decode($project_row['project_name']);
			$project_link = '<a href="' . $base_url . '/projects.php?id=' . $project_id . '">' . $project_name . '</a>';
			
			$log_message = "$user_realname added message " . $message_link . " to project " . $project_link . ".";
			
			//get all assigned users and alert them
			$project_users = $this->get_assigned_users('project', $project_id, false);
			foreach($project_users as $user_id => $user_realname)
			{
				$this->dashboard_log($author_user_id, $user_id, $this->timestamp, $log_message);
			}
		}
		
		return true;
			
	}
	
	function add_task_message($task_id, $message_id, $log = true)
	{
		global $db, $user;
		
		$author_user_id = (int) $user->data['user_id'];
		
		if(!$message_id || !$task_id)
		{
			return false;
		}
		
		$row = array(
			'message_id'	=>	(int) $message_id,
			'task_id'	=>	(int) $task_id,
		);
		
		$sql = 'INSERT INTO '.TASK_MESSAGES_TABLE.' ' . $db->sql_build_array('INSERT', $row);
		$db->sql_query($sql);
		
		if($log)
		{
			$user_row = $this->user_details($author_user_id);
			$user_realname = $user_row['user_realname'];
		
			$logurl = $base_url . "/tasks.php?id=" . $task_id;
			$message_row = $this->get_message_detail($message_id);
			$subject = $message_row['subject'];
			$message_link = '<a href="'.$logurl.'">'.$subject.'</a>';
			
			$project_row = $this->get_project_detail($project_id);
			$project_name = litwicki_decode($project_row['project_name']);
			$project_link = '<a href="' . $base_url . '/projects.php?id=' . $project_id . '">' . $project_name . '</a>';
			
			$log_message = "$user_realname left a note on one of your task assignments: " . $message_link . ".";
			
			//get all assigned users and alert them
			$task_users = $this->get_assigned_users('task', $task_id, false);
			foreach($task_users as $user_id => $user_realname)
			{
				$this->dashboard_log($author_user_id, $user_id, $this->timestamp, $log_message);
			}
		}
		
		return true;
			
	}
	
	function modify_message($message_id, $message_row)
	{
		global $db;
		
		$project_id 	= $message_row['project_id'];
		$subject 		= $message_row['subject'];
		$message 		= $message_row['message'];
		
		$sql = "UPDATE 
					".MESSAGES_TABLE." 
				SET 
					date_updated=$this->timestamp, 
					project_id=$project_id, 
					subject='$subject', 
					message='$message',
					reply_date=NULL
				WHERE 
					message_id=$message_id";
					
		$db->sql_query($sql);
		
		//because we updated this, mark it unread
		$this->mark_message_unread($message_id);
	}
	//end modify_message()
	
	function message_attach($attachments, $message_id)
	{
		global $root_path, $user, $file;
		
		$user_id = (int) $user->data['user_id'];

		for($i = 0; $i <= count($attachments); $i++ )
		{
			$oldfile = $attachments['tmp_name'][$i];
			$filename = $attachments['name'][$i];
			$filename = str_replace(" ","_",$filename);
			
			$file_ext = preg_replace("/.*\.(.*?)/","$1",$filename);
			
			$unique_filename = $message_id . "_" . time() . "." . $file_ext;
			$unique_filename = str_replace(" ","_",$unique_filename);

			$newfile = FILE_PATH . '/files/' . $unique_filename;
			
			if( move_uploaded_file($oldfile, $newfile) )
			{
				//file was uploaded, so let's build the attachment array
				$file_row = array(
					'filename'			=>	$filename,
					'unique_filename'	=>	$unique_filename,
					'filesize'			=>	filesize($newfile),
					'file_ext'			=>	$file_ext,
					'date_added'		=>	time(),
					'user_id'			=>	$user_id,
				);
				
				$attachment_id = $this->add_attachment($message_id, $file_row);
				unset($file_row);
				
				//use this id to rename this file, so we don't overwrite additional files
				if( $file->rename($attachment_id) )
				{
					$attach_ids[] = $attachment_id;
				}
			}
		}

		return $attach_ids;
	}

	function add_attachment($message_id, $file_row)
	{
		global $db, $file;
		
		if( !$message_id || !$file_row )
		{
			return false;
		}
		
		//first add the file
		$file_id = $file->add($file_row);
		
		$attachment_row = array(
			'message_id'	=>	(int) $message_id,
			'file_id'		=>	(int) $file_id,
		);

		$sql = 'INSERT INTO '.ATTACHMENTS_TABLE.' ' . $db->sql_build_array('INSERT', $attachment_row);
		$db->sql_query($sql);
		$attachment_id = (int) $db->sql_nextid();
		
		return $attachment_id;
	}
	
	//marks a message read so it displays on the dashboard homepage
	//messages are only marked read manually by a user
	function mark_message_read($message_id, $user_id)
	{
		global $db;
		$sql = "INSERT INTO ".READ_MESSAGES_TABLE." (message_id, user_id, date_read) VALUES ($message_id, $user_id, $this->timestamp)";
		$db->sql_query($sql);
		return false;
	}
	
	function mark_message_unread($message_id)
	{
		global $db;
		$sql = "DELETE FROM ".READ_MESSAGES_TABLE." WHERE message_id=$message_id";
		$db->sql_query($sql);
		return false;
	}
	
	function add_message_reply($message_id, $parent_id)
	{
		global $db, $base_url;

		$sql = "INSERT INTO ".REPLIES_TABLE." (message_id, parent_id) VALUES ($message_id, $parent_id)";
		$db->sql_query($sql);
		$reply_id = $db->sql_nextid();
		
		//now that there's a reply, make sure this parent_id isn't marked "read" by anyone
		$this->mark_message_unread($parent_id);
		
		//get project user_id for owner_id
		$message_row = $this->get_message_detail($parent_id, false);
		$user_id = $message_row['user_id'];
		$message_subject = $message_row["subject"];
		
		unset($message_row);

		/*
		* Now that nobody has this message marked, let's update the parent_id so when it
		* reappears on the home dashboard the users know why. And just to be safe, update
		* the status to active (may be redundant)
		*/
		
		$sql = "UPDATE ".MESSAGES_TABLE." SET reply_date=$this->timestamp, status=$this->open_status WHERE message_id=$parent_id";
		$db->sql_query($sql);

		return $reply_id;
		
	}
	
	//get proposal for a specified project_id
	function get_project_proposal($project_id)
	{
		global $db;
		
		$sql = "SELECT
					MAX(pp.message_id) AS message_id, pp.status
				FROM
					".PROPOSALS_TABLE." pp
					JOIN ".MESSAGES_TABLE." m ON m.message_id=pp.message_id
					JOIN ".PROJECTS_TABLE." p ON p.project_id=pp.project_id
				WHERE
					pp.project_id=$project_id
				GROUP BY
					pp.status";
					
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrow($result);

		$message_id = $row['message_id'];
		$proposal_status = $row['status'];
		
		if($message_id)
		{
			$proposal_row = $this->get_message_detail($message_id, false);

			$db->sql_freeresult($result);
			
			return $proposal_row;
		}
		
		$db->sql_freeresult($result);
		
		return false;
	}

	//Get message_id for proposal then use $this->get_message_detail()
	function get_proposal_detail($proposal_id, $output = true)
	{
		global $db, $template;

		if(!$proposal_id)
		{
			return false;
		}
		
		//get the message_id for this request
		$sql = "SELECT message_id, request_id, status as proposal_status FROM ".PROPOSALS_TABLE." WHERE proposal_id=$proposal_id";
		$result = $db->sql_query($sql);
		$proposal_row = $db->sql_fetchrow($result);
		
		$message_id = (int) $proposal_row['message_id'];
		$request_id = (int) $proposal_row['request_id'];
		$proposal_status = (int) $proposal_row['proposal_status'];
		
		if($message_id)
		{
			//get all the details for this proposal message
			$row = $this->get_message_detail($message_id, $output);
			$row['request_id'] = $request_id;
			$row['message_id'] = $message_id;
			$row['proposal_status'] = $proposal_status;
			return $row;
		}
		
		$db->sql_freeresult($result);
		
		return $true;
	}
	// end get_proposal_detail()
	
	//$attachments array() simply passed through to add_message
	function add_proposal($proposal_row)
	{
		global $db;
		
		$project_id		= $proposal_row['project_id'];
		$author_id		= $proposal_row['author_id'];
		$subject		= $proposal_row['subject'];
		$message		= $proposal_row['message'];
		
		/**
		 *	Get the request_id associated to this project
		 *	if there isn't one then the project was created
		 *	manually, so just assign the request_id to 0.
		 */
		
		$sql = "SELECT
					request_id
				FROM
					".MESSAGES_TABLE." m
					JOIN ".REQUESTS_TABLE." r ON r.message_id=m.message_id
				WHERE
					r.project_id=$project_id";

		$result = $db->sql_query($sql);
		$request_count = $db->sql_affectedrows($result);

		$row = $db->sql_fetchrow($result);
		$request_id = (int) $row['request_id'];

		unset($row);
		unset($sql);
		$db->sql_freeresult($result);

		$message_row = array(
			'date_added'	=>	$this->timestamp,
			'user_id'		=>	$author_id,
			'subject'		=>	$subject,
			'message'		=>	$message,
		);

		$owner_id = $this->project_owner($project_id);
		
		//add the proposal message
		$message_id = $this->add_message($message_row, false, false);
		
		unset($message_row);
		
		//are there any existing proposals? If so we need to "close" them so
		//the newest is the only relevant one, but keep them in the database for versioning

		$sql = "SELECT *
				FROM
					".MESSAGES_TABLE." m
					JOIN ".PROPOSALS_TABLE." p ON p.message_id=m.message_id
				WHERE
					p.project_id=$project_id";

		$result = $db->sql_query($sql);
		$proposal_count = $db->sql_affectedrows($result);

		//there are existing proposals for this project, disable/archive them
		if( $proposal_count > 0 )
		{
			while( $row = $db->sql_fetchrow($result) )
			{
				$this->change_status('proposal', $row['proposal_id'], $this->closed_status);
			}
		}
		
		$proposal_row_new = array(
			'message_id'	=>	$message_id,
			'request_id'	=>	$request_id,
			'project_id'	=>	$project_id,
			'status'		=>	$this->open_status,
			'user_id'		=>	$author_id,
			'date_added'	=>	$this->timestamp,
		);

		//add the message
		$sql = 'INSERT INTO '.PROPOSALS_TABLE.' ' . $db->sql_build_array('INSERT', $proposal_row_new);
		$db->sql_query($sql);
		$proposal_id = $db->sql_nextid();
		
		unset($proposal_row);
		
		//now update the request status so we know there isn't an urgent need
		$sql = "UPDATE ".REQUESTS_TABLE." SET status=2, status_date=$this->timestamp WHERE request_id=$request_id";
		$db->sql_query($sql);
		
		//finally, update the project start_date (we'll do this again later, this is mostly for display on the proposal page)
		$sql = "UPDATE ".PROJECTS_TABLE." SET start_date=$this->timestamp WHERE project_id=$project_id";
		$db->sql_query($sql);

		$user_row = $this->user_details($author_id);
		$user_realname = $user_row['user_realname'];
	
		$proposal_url = $base_url . "/proposals.php?id=$proposal_id";
		$proposal_link = '<a href="'.$proposal_url.'">'.$subject.'</a>';
		
		$request_url = "$base_url/requests.php?id=$request_id";
		$request_link = '<a href="'.$request_url.'">request #'.$request_id.'</a>';
		
		$log_message = "$user_realname submitted a proposal ($proposal_link) for request ($request_link).";
		
		$this->dashboard_log($author_id, $owner_id, $this->timestamp, $log_message);

		return $proposal_id;
	}
	//end add_proposal()
	
	function modify_proposal($proposal_row)
	{
		global $db, $base_url, $user;
		
		$user_realname = $user->data['user_realname'];
		$user_id = $user->data['user_id'];

		$sql = 'UPDATE ' . MESSAGES_TABLE . ' SET ' . $db->sql_build_array('UPDATE', $proposal_row) . ' WHERE message_id = ' . (int) $message_id;
		$db->sql_query($sql);
		
		//get request_id to update
		$sql = "SELECT request_id FROM ".PROPOSALS_TABLE." WHERE message_id=$message_id";
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrow($result);
		$request_id = $row['request_id'];
		
		//because we updated this, mark it unread
		$this->mark_message_unread($message_id);
		$this->change_status('proposal', $proposal_id, $this->open_status);
		$this->change_status('request', $request_id, 2);
		
		//log this
		$proposal_link = "$base_url/proposals.php?id=$proposal_id";
		$proposal_name = $proposal_row['subject'];
		$owner_id = $this->project_owner($project_id);
		
		$log_message = $user_realname . ' modified proposal: <a href="'.$proposal_link.'">'.$proposal_name.'</a>';
		$this->dashboard_log($user_id, $owner_id, $this->timestamp, $log_message);
		
	}
	//end modify_message()

	function add_proposal_request($request_row)
	{
		global $db, $admin_user_id, $base_url;

		$message_row = $this->get_message_detail($request_row['message_id']);
		$request_subject = $message_row['subject'];
		
		$user_row = $this->user_details($message_row['user_id']);
		$user_realname = $user_row['user_realname'];
		$user_id = $user_row['user_id'];
		
		unset($message_row);
		
		$sql = 'INSERT INTO '.REQUESTS_TABLE.' ' . $db->sql_build_array('INSERT', $request_row);
		$db->sql_query($sql);
		$request_id = $db->sql_nextid();
		
		//log this
		$request_link = "$base_url/requests.php?id=$request_id";
		
		$log_message = $user_realname . ' submitted a request for proposal: <a href="'.$request_link.'">'.$request_subject.'</a>!';
		$this->dashboard_log($user_id, $admin_user_id, $this->timestamp, $log_message);

		return $request_id;
	}
	
	function get_attachments($message_id, $status = 1, $output = true)
	{
		global $db, $base_url, $template, $config;
		
		$sql = "SELECT * 
				FROM 
					".ATTACHMENTS_TABLE." a 
					JOIN ".FILES_TABLE." f ON f.file_id=a.file_id 
				WHERE 
					a.message_id=$message_id 
					AND f.status=$status";
					
		$result = $db->sql_query($sql);
		
		$attach_count = $db->sql_affectedrows($result);
		
		if($output)
		{
			while( $row = $db->sql_fetchrow($result) )
			{
				$unique_filename = $row['unique_filename'];
				$file_link = "$base_url/files/$unique_filename";

				$row['FILELINK']		=	$file_link;
				$row['FILESIZE']		=	filesize_format($row['filesize']);
				$row['DATE_ADDED']		=	date($config['date_long'], $row['date_added']);

				$attachrow[] = array_change_key_case($row, CASE_UPPER);
			}
			
			$template->assign('attachrow', $attachrow);
			
		}
		
		return $attachrow;
		
	}

	function assign($type, $user_id, $type_id, $send_email = false, $growl = true)
	{
		global $db, $user, $base_url;
		
		$my_user_id = $user->data['user_id'];
		$my_username = $user->data['user_realname'];
		$assigned_user = get_user_val("user_realname", $user_id);
		
		if( $type == 'task' )
		{
			$user_row = array(
				'user_id'		=>	$user_id,
				'task_id'		=>	$type_id,
				'date_added'	=>	time(),
			);
			
			$sql = 'INSERT INTO '.TASK_USERS_TABLE.' ' . $db->sql_build_array('INSERT', $user_row);
			$db->sql_query($sql);

			$logurl 	= $base_url . "/tasks.php?id=$type_id";
			$taskrow 	= $this->get_task_detail($type_id, false);
			$task_name 	= litwicki_decode($taskrow['task_name']);
			$subject 	= 'Assigned to Task: ' . $task_name;
			
			$log_message = 'You were assigned to task "<a href="'.$logurl.'">'.$task_name.'</a>' . ($my_user_id == $user_id ? '."' : ' by ' . $my_username) . '';
		}
		elseif( $type == 'project' )
		{
			$user_row = array(
				'user_id'		=>	$user_id,
				'project_id'	=>	$type_id,
				'date_added'	=>	time(),
			);
			
			$sql = 'INSERT INTO '.PROJECT_USERS_TABLE.' ' . $db->sql_build_array('INSERT', $user_row);
			$db->sql_query($sql);
			
			$logurl 		= $base_url . "/projects.php?id=$type_id";
			$row 			= $this->get_project_detail($type_id, false);
			$project_name 	= litwicki_decode($row['project_name']);
			$subject 		= 'Added to Project: ' . $project_name;
			
			$log_message = 'You were added to project "<a href="'.$logurl.'">'.$project_name.'</a>' . ($my_user_id == $user_id ? '."' : ' by ' . $my_username) . '';
		}
		
		if($growl)
		{
			$this->dashboard_log($my_user_id, $user_id, $this->timestamp, $log_message);
		}
		
		if($send_email)
		{
			email_user($subject, $log_message, $user_id, $html_email = false, $priority = 3, $attachments = false);
		}
		
		return true;
	}
	
	function add_pm($user_id, $project_id, $send_email = false, $growl = true)
	{
		global $db, $user, $base_url;
		
		$my_user_id = $user->data['user_id'];
		$my_username = $user->data['user_realname'];
		$assigned_user = get_user_val("user_realname", $user_id);

		$user_row = array(
			'user_id'		=>	$user_id,
			'project_id'	=>	$project_id,
			'date_added'	=>	time(),
		);
		
		$sql = 'INSERT INTO '.PROJECT_MANAGERS_TABLE.' ' . $db->sql_build_array('INSERT', $user_row);
		$db->sql_query($sql);
		
		$logurl 		= $base_url . "/projects.php?id=$project_id";
		$row 			= $this->get_project_detail($project_id, false);
		$project_name 	= litwicki_decode($row['project_name']);
		$subject 		= 'You Were Made Project Manager: ' . $project_name;
		
		$log_message = 'You were added as Project Manager to project "<a href="'.$logurl.'">'.$project_name.'</a>' . ($my_user_id == $user_id ? '."' : ' by ' . $my_username) . '';
		
		if($growl)
		{
			$this->dashboard_log($my_user_id, $user_id, $this->timestamp, $log_message);
		}
		
		if($send_email)
		{
			email_user($subject, $log_message, $user_id, $html_email = false, $priority = 3, $attachments = false);
		}
		
		return true;
	}
	
	function remove_pm($user_id, $project_id, $send_email = false, $growl = true)
	{
		global $db, $user, $base_url;
		
		$my_user_id 	= $user->data['user_id'];
		$my_username 	= $user->data['user_realname'];
		$assigned_user 	= get_user_val("user_realname", $user_id);

		$sql = "DELETE FROM ".PROJECT_MANAGERS_TABLE." WHERE user_id=$user_id AND project_id=$project_id";
		$db->sql_query($sql);

		$logurl 		= $base_url . "/projects.php?id=$project_id";
		$projectrow 	= $this->get_project_detail($project_id);
		$project_name 	= litwicki_decode($projectrow['project_name']);
		$subject 		= 'Removed as Project Manager to Project: ' . $project_name;
		
		$log_message = 'You were removed as project manager from project "'.$project_name.'"' . ($my_user_id == $user_id ? '."' : ' by ' . $my_username) . '';
	
		if($growl)
		{
			$this->dashboard_log($my_user_id, $user_id, $this->timestamp, $log_message);
		}
		
		if($send_email)
		{
			email_user($subject, $log_message, $user_id, $html_email = false, $priority = 3, $attachments = false);
		}
		
		return true;
	}

	function unassign($type, $user_id, $type_id, $send_email = false, $growl = true)
	{
		global $db, $user, $base_url;
		
		$my_user_id 	= $user->data['user_id'];
		$my_username 	= $user->data['user_realname'];
		$assigned_user 	= get_user_val("user_realname", $user_id);
		
		if( $type == 'task' )
		{
			$sql = "DELETE FROM ".TASK_USERS_TABLE." WHERE user_id=$user_id AND task_id=$type_id";
			$db->sql_query($sql);

			$logurl 	= $base_url . "/tasks.php?id=$type_id";
			$taskrow 	= $this->get_task_detail($type_id);
			$task_name 	= $taskrow['task_name'];
			$subject 	= 'Task Assignment Removed';
			
			$log_message = 'You were un-assigned from task "<a href="'.$logurl.'">'.$task_name.'</a>' . ($my_user_id == $user_id ? '."' : ' by ' . $my_username) . '';
		}
		elseif( $type == 'project' )
		{
			$sql = "DELETE FROM ".PROJECT_USERS_TABLE." WHERE user_id=$user_id AND project_id=$type_id";
			$db->sql_query($sql);

			$logurl 		= $base_url . "/projects.php?id=$type_id";
			$projectrow 	= $this->get_project_detail($type_id);
			$project_name 	= litwicki_decode($projectrow['project_name']);
			$subject 		= 'Removed from Project: ' . $project_name;
			
			$log_message = 'You were removed from project "<a href="'.$logurl.'">'.$project_name.'</a>' . ($my_user_id == $user_id ? '."' : ' by ' . $my_username) . '';
		}
		
		if($growl)
		{
			$this->dashboard_log($my_user_id, $user_id, $this->timestamp, $log_message);
		}
		
		if($send_email)
		{
			email_user($subject, $log_message, $user_id, $html_email = false, $priority = 3, $attachments = false);
		}
		
		return true;
	}
	
	/**
	 *	@purpose: Process selected/assigned users, and assign/remove as specified
	 *				If a user is selected, but not assigned, add.
	 *				If a user is NOT selected, but assigned, remove.
	 *
	 *	@type............: task|project
	 *	@type_id.........: $task_id|$project_id
	 *	@assigned_users..: array[user_id] = user_realname
	 *	@selected_users..: array[count] = user_id
	 */
	 
	function manage_user_assignments($type, $type_id, $selected_users, $assigned_users)
	{
		global $db;

		//There MUST always be at least one selected_user()
		if( empty($selected_users) || $type == '' || !$type_id )
		{
			return false;
		}

		/**
		 *	Loop through all ASSIGNED users.
		 *	If they are not in the SELECTED list, remove them.
		 *	When a task/project is first created, there won't be any assigned_users,
		 *	so simply skip this step if assigned_users() is empty.
		 */

		if( !empty($assigned_users) )
		{
			foreach($assigned_users as $assigned_user_id => $assigned_username)
			{
				if( !in_array($assigned_user_id, $selected_users) )
				{
					$this->unassign($type, $assigned_user_id, $type_id, $send_email = true, $growl = true);
				}
			}
		}
		
		/**
		 *	Loop through all SELECTED users.
		 *	If they are not in the assigned_users array then assign them.
		 *	If the assigned_users() is blank, we're adding a brand new task/project,
		 *	so there naturally won't be any assigned_users yet. In this case
		 *	just skip the array_key check and assign the user.
		 */
		
		foreach($selected_users as $key => $selected_user_id)
		{
			//first check if the user is assigned
			if( !empty($assigned_users) )
			{
				if( !array_key_exists($selected_user_id, $assigned_users) )
				{
					$this->assign($type, $selected_user_id, $type_id, $send_email = true, $growl = true);
				}
			}
			else
			{
				$this->assign($type, $selected_user_id, $type_id);
			}
		}
		
		return true;
	}
	
	/**
	 *	Severe code duplication from manage_user_assignments()
	 *	TODO: Optimize this and manage_user_assignments()
	 */
	
	function manage_pm($project_id, $selected_users, $assigned_users)
	{
		global $db;

		//There MUST always be at least one selected_user()
		if( empty($selected_users) || !$project_id )
		{
			return false;
		}
		
		/**
		 *	Loop through all ASSIGNED users.
		 *	If they are not in the SELECTED list, remove them.
		 *	When a task/project is first created, there won't be any assigned_users,
		 *	so simply skip this step if assigned_users() is empty.
		 */

		if( !empty($assigned_users) )
		{
			foreach($assigned_users as $assigned_user_id => $assigned_username)
			{
				if( !in_array($assigned_user_id, $selected_users) )
				{
					$this->remove_pm($assigned_user_id, $project_id, $send_email = true, $growl = true);
				}
			}
		}
		
		/**
		 *	Loop through all SELECTED users.
		 *	If they are not in the assigned_users array then assign them.
		 *	If the assigned_users() is blank, we're adding a brand new task/project,
		 *	so there naturally won't be any assigned_users yet. In this case
		 *	just skip the array_key check and assign the user.
		 */
		
		foreach($selected_users as $key => $selected_user_id)
		{
			//first check if the user is assigned
			if( !empty($assigned_users) )
			{
				if( !array_key_exists($selected_user_id, $assigned_users) )
				{
					$this->add_pm($selected_user_id, $project_id, $send_email = true, $growl = true);
				}
			}
			else
			{
				$this->add_pm($selected_user_id, $project_id);
			}
		}
		
		return true;
	}
	
	function change_status($type, $item_id, $status)
	{
		global $db, $user;
		
		$user_id = $user->data['user_id'];

		$item 			= $this->item_type($type);
		$table_name 	= $item['table_name'];
		$table_pk 		= $item['primary_key'];
		
		$status_row = array(
			'status'		=>	$status,
			'status_date'	=>	$this->timestamp,
		);

		$sql = 'UPDATE ' . $table_name . ' SET ' . $db->sql_build_array('UPDATE', $status_row) . ' WHERE ' . $table_pk . ' = ' . (int) $item_id;
		$db->sql_query($sql);

		unset($status_row);
		
		/**
		 *	If we're closing/deleting a request, we need to also close the project
		 *	associated with it so there isn't an orphaned project.
		 */
		 
		if( $type == "request" && $status == $this->closed_status )
		{
			//get the project_id for this request
			$sql = "SELECT project_id FROM ".REQUESTS_TABLE." WHERE request_id=$id";
			$result = $db->sql_query($sql);
			$row = $db->sql_fetchrow($result);
			$project_id = (int) $row['project_id'];
			
			$this->change_status('project', $project_id, $this->closed_status);
		}

		switch($status)
		{
			case 0:
				$status_name = "Closed";
				break;
			case 1:
				$status_name = "Opened";
				break;
			case 2:
				$status_name = "Approved";
				break;
		}
		
		$assigned_user = get_user_val("user_realname", $user_id);
		//TODO: log this
		
		$db->sql_freeresult($result);
		
		return false;

	}
	
	/*
	 *	Build a list of projects for association with a proposal or message
	 *	@project_id (int): project_id to be auto-selected
	 *	@show_all (boolean): should we show ALL projects, or only projects that are active?
	 */
	 
	function get_projectlist($type, $project_id = false, $show_all = false)
	{
		global $db, $template, $auth, $user;

		$user_id = (int) $user->data['user_id'];

		if($type == 'task')
		{
			$auth_type = 'U_ADD_TASK';
		}
		elseif($type == 'message')
		{
			$auth_type = 'U_ADD_MESSAGE';
		}
		elseif($type == 'file')
		{
			$auth_type = 'U_ADD_FILE';
		}
		elseif($type == 'proposal')
		{
			$auth_type = 'U_ADD_PROPOSAL';
		}
		else
		{
			return false;
		}
		
		if( !$auth->user_group['S_STAFF'] )
		{
			$sql = " SELECT
					p.*, c.*, u.user_realname 
				FROM
					".PROJECTS_TABLE." p
					JOIN ".USERS_TABLE." u ON u.user_id=p.user_id
					JOIN ".CLIENTS_TABLE." c ON c.client_id=p.client_id
					JOIN ".CLIENT_USERS_TABLE." cu ON cu.client_id=c.client_id
				WHERE
					p.status " . $show_all == true ? " <> 0 " : " = 1
					AND cu.user_id=$user_id
				ORDER BY
					c.company, p.project_id";
		}
		else
		{
			$sql = "SELECT
					p.*, c.*, u.user_realname 
				FROM
					".PROJECTS_TABLE." p
					JOIN ".USERS_TABLE." u ON u.user_id=p.user_id
					JOIN ".CLIENTS_TABLE." c ON c.client_id=p.client_id
				WHERE
					p.status " . ($show_all == true ? " <> 0 " : " = 1 ") . "
				ORDER BY
					c.company, p.project_id";
		}

		$result = $db->sql_query($sql);
		
		while( $row = $db->sql_fetchrow($result) )
		{
			$skip_project = false;
			
			$this_project_id = (int) $row['project_id'];

			//Does this user have permission to $auth_type for this project?
			if( !empty($auth->auth_options_project[$this_project_id]) )
			{
				if( $auth->auth_options_project[$this_project_id][$auth_type] == 0 )
				{
					$skip_project = true;
				}
			}

			//do not display a project if a contract is required, and a proposal is not found
			if( $row['contract_required'] )
			{
				$sql_ = "SELECT 
							proposal_id 
						FROM 
							".PROPOSALS_TABLE." pp
							JOIN ".MESSAGES_TABLE." m ON m.message_id=pp.message_id 
						WHERE 
							pp.project_id=$this_project_id";
							
				$result_ = $db->sql_query($sql_);
				$row_ = $db->sql_fetchrow($result_);
				
				$proposal_id = (int) $row_['proposal_id'];
				if( $proposal_id == 0 )
				{
					$skip_project = true;
				}
			}
			
			if(!$skip_project)
			{
				$row['PROJECT_NAME']		=	$row['project_name'] . " (" . $row['company'] . ")";
				$row['SELECTED_PROJECT']	=	$project_id == $this_project_id ? true : false;
				
				$projectlist[] = array_change_key_case($row, CASE_UPPER);
			}
			
			unset($skip_project);
			unset($sql_);
			unset($row_);
			$db->sql_freeresult($result_);
		}
		
		$template->assign('projectlist', $projectlist);
		
		return true;
	}
	
	function mycompanies($user_id, $output = true)
	{
		global $db, $template;
		
		$user_id = (int) $user_id;
		
		$sql = "SELECT * 
				FROM 
					".CLIENTS_TABLE." c 
					LEFT JOIN ".CLIENT_USERS_TABLE." cu ON c.client_id=cu.client_id 
				WHERE 
					cu.user_id=$user_id 
					AND c.status=1 
				ORDER BY 
					c.company";

		$result = $db->sql_query($sql);
		
		while( $row = $db->sql_fetchrow($result) )
		{
			//$row['PHONE'] 			= 	preg_replace("/(\d{3})(\d{3})(\d{4})/","($1) $2-$3",$row['phone']);
			$row['PHONE']			=	parse_phone($row['phone']);
			$row['FULL_ADDRESS']	= 	$row['address'] . ' ' . $row['city'] . ' ' . $row['state'] . ', ' . $row['zip'];
			$row['EMAIL']			=	$row['email'] == 1 ? true : false;
			
			$companyrow[] = array_change_key_case($row, CASE_UPPER);
			$mycompanies[$row['client_id']] = $row['company'];
		}
		
		if($output)
		{
			$template->assign('clientlist', $companyrow);
			
			$template->assign(array(
				'SHOW_CLIENT_LIST'	=>	$db->sql_affectedrows($result) > 0 ? true : false,
			));
		}
		
		return $mycompanies;
	}
	
	/**
	 *	Get a complete list of all clients.
	 *	For a specific user, pre-select the client_ids they are users for.
	 */
	function get_all_clients($user_id, $output = true)
	{
		global $db, $template;
		
		$client_users = $this->mycompanies($user_id, false);

		$sql = "SELECT * FROM ".CLIENTS_TABLE." WHERE status=1 ORDER BY company";
		$result = $db->sql_query($sql);
		
		while( $row = $db->sql_fetchrow($result) )
		{
			$row['SELECTED'] = empty($client_users) ? false : array_key_exists($row['client_id'], $client_users) ? true : false;
			
			$clientlist[] = array_change_key_case($row, CASE_UPPER);
			$client_row[$row['client_id']] = $row['company'];
		}
		
		if($output)
		{
			$template->assign('clientlist', $clientlist);	
		}
		
		return $client_row;
	}

	/**
	 *	Build a list of clients for drop-down-menus in forms etc.
	 */
	function get_clientlist($project_id = false, $user_id = false, $output = true)
	{
		global $db, $template;

		if($project_id && $user_id)
		{
			//wtf is going on?
			return false;
		}
		
		if($user_id)
		{
			/**
			 *	user_id specified means we want to get all of the
			 *	companies a specific user is part of
			 */
			
			$sql = "SELECT *
					FROM
						".CLIENTS_TABLE." c
						JOIN ".CLIENT_USERS_TABLE." cu ON cu.client_id=c.client_id
					WHERE
						c.status=1
						AND cu.user_id=$user_id";
		}
		else
		{
			/**
			 *	If no user_id is specified, then we're just 
			 *	looking for a complete list of companies we have.
			 */
			 
			$sql = "SELECT * 
				FROM 
					".CLIENTS_TABLE."
				WHERE
					status=1 
				ORDER BY
					company";
		}

		$result = $db->sql_query($sql);
		
		while( $row = $db->sql_fetchrow($result) )
		{
			if($project_id)
			{
				$projectrow = $this->get_project_detail($project_id, false);
				if($projectrow['client_id'] == $row['client_id'])
				{
					$selected_client = true;
				}
				else
				{
					$selected_client = false;
				}
			}
			
			//$row['PHONE'] 			=	preg_replace("/(\d{3})(\d{3})(\d{4})/","($1) $2-$3",$row['phone']);
			$row['PHONE']			=	parse_phone($row['phone']);
			$row['FULL_ADDRESS'] 	=	$row['address'] . '<br />' . $row['city'] . ' ' . $row['state'] . ', ' . $row['zip'];
			$row['ADDRESS']			=	$row['address'];
			$row['SELECTED']		=	$selected_client;

			$clientlist[] = array_change_key_case($row, CASE_UPPER);
		
		}

		if($output)
		{
			$template->assign('clientlist', $clientlist);
		}
		
		$db->sql_freeresult($result);
		return $clientlist;

	}
	
	function get_clientusers($project_id = false, $user_id = false)
	{
		global $db;
		
		$project_id = (int) $project_id;
		$user_id = (int) $user_id;
		
		//if both are, or both are not specified, we cannot do anything here
		if( (!$project_id && !$user_id) || $project_id > 0 && $user_id > 0 )
		{
			return false;
		}
		
		$sql = "SELECT 
					c.company, c.client_id, u.user_realname, u.user_id 
				FROM
					".CLIENTS_TABLE." c
					LEFT JOIN ".PROJECTS_TABLE." p ON p.client_id=c.client_id
					JOIN ".CLIENT_USERS_TABLE." cu ON cu.client_id=c.client_id
					JOIN ".USERS_TABLE." u ON u.user_id=cu.user_id
				WHERE
					u.user_id > 0 ";

		if( $project_id )
		{
			$sql .= " AND p.project_id=$project_id";
		}
		else
		{
			$sql .= " AND u.user_id=$user_id";
		}
				
		$result = $db->sql_query($sql);
		while( $row = $db->sql_fetchrow($result) )
		{
			$client_users = $row;
		}
		
		return $client_users;
		
	}
	
	/**
	 *	Build a list of users.
	 *	Allow toggle between staff_only & ALL users
	 */
	
	function client_admin_user($client_id)
	{
		global $db;
		
		$sql = "SELECT user_id FROM ".CLIENT_USERS_TABLE." WHERE client_id=$client_id AND super=1";
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrow($result);
	
		return $row['user_id'];
	}
	
	function set_client_admin($client_id, $user_id)
	{
		global $db;
		
		//first reset all users to 0
		$sql = "UPDATE ".CLIENT_USERS_TABLE." SET super=0 WHERE client_id=$client_id";
		$db->sql_query($sql);
		
		$sql = "UPDATE ".CLIENT_USERS_TABLE." SET super=1 WHERE client_id=$client_id AND user_id=$user_id";
		$db->sql_query($sql);
		
		return true;
	}
	
	function myusers($staff_only = false)
	{
		$users = $this->get_userlist($staff_only);
		return count($users);
	}
	
	function get_userlist($staff_only = false, $output = true)
	{
		global $db, $template, $base_url, $user, $config;
		
		if($staff_only)
		{
			$sql = "SELECT *
					FROM
						".USERS_TABLE." u
						JOIN ".USER_GROUP_TABLE." ug ON u.user_id=ug.user_id
						JOIN ".GROUPS_TABLE." g ON g.group_id=ug.group_id
					WHERE
						g.group_name='STAFF' 
						AND u.status=1 
					ORDER BY
						u.user_lastname";
		}
		else
		{
			$sql = "SELECT * 
				FROM 
					".USERS_TABLE."
				WHERE
					status=1 
				ORDER BY 
					user_lastname";
		}
		
		$result = $db->sql_query($sql);

		$user_count = 0;
		
		while( $row = $db->sql_fetchrow($result) )
		{
			$this_user_id = (int) $row['user_id'];
			
			//get groups user belongs to
			$user_groups = $user->mygroups($this_user_id);
			
			$gender_icon = ($row['user_gender'] == 'f' ? 'user-female' : 'user-male');

			$row['USER_NAME']		=	($row['user_lastname'] == "" ? '' : $row['user_lastname'] . ', ') . $row['user_firstname'];
			$row['USER_PHONE']		=	parse_phone($row['user_phone']);
			$row['USER_REGDATE']	=	date($config['date_short'], $row['user_regdate']);
			$row['USER_CLIENTS']	=	$user_clients;
			$row['GENDER_ICO']		=	$gender_icon;
			$row['S_STAFF']			= 	in_array('STAFF',$user_groups) ? true : false;
			$row['S_CLIENT']		= 	in_array('CLIENT',$user_groups) ? true : false;
			$row['S_CONTRACTOR']	= 	in_array('CONTRACTOR',$user_groups) ? true : false;
			$row['S_MANAGER']		= 	in_array('MANAGER',$user_groups) ? true : false;

			$user_row[] = array_change_key_case($row, CASE_UPPER);

			$users[$row['user_id']] = $row['user_realname'];
			
		}

		$db->sql_freeresult($result);
		
		if($output)
		{
			$template->assign('userlist', $user_row);
		}
		
		return $users;
	}
	
	function get_project_managers($project_id, $output = true)
	{
		global $db, $template;
		
		$project_id = (int) $project_id;

		//get full list of staff
		$staff_users = $this->get_userlist($staff_only = true, false);

		//get list of all currently assigned managers
		$sql = "SELECT user_id FROM ".PROJECT_MANAGERS_TABLE." WHERE project_id=$project_id";
		$result = $db->sql_query($sql);
		while( $row = $db->sql_fetchrow($result) )
		{
			$assigned_managers[] = $row['user_id'];
		}
		
		$db->sql_freeresult($result);
		
		foreach($staff_users as $this_user_id => $this_user_realname)
		{
			$user_gender = get_user_val('user_gender', $this_user_id);
			$gender_icon = ($user_gender == 'f' ? 'user-female' : 'user-male');
			$user_assigned = (in_array($this_user_id, $assigned_managers) ? true : false);
			
			$manager = array(
				'USER_REALNAME'		=>	$this_user_realname,
				'USER_ID'			=>	$this_user_id,
				'GENDER_ICO'		=>	$gender_icon,
				'USER_PHONE'		=>	get_user_val('user_phone', $this_user_id),
				'ASSIGNED'			=>	$user_assigned,
			);
			
			$managerlist_array[] = $manager;
			
			if($user_assigned)
			{
				$managerlist[$this_user_id] = $this_user_realname;
			}

			unset($manager);
		}
		
		if($output)
		{
			$template->assign('projectmanager_row', $managerlist_array);
		}

		return $managerlist;
	
	}
	
	//display list of tasks in dropdown menu when adding a new message
	//as a means of displaying a particular message in task_detail
	function get_tasklist($project_id)
	{
		global $db;
		$sql = "SELECT task_id, task_name FROM ".TASKS_TABLE." WHERE project_id=$project_id ORDER BY task_name";
		$result = $db->sql_query($sql);
		while( $row = $db->sql_fetchrow($result) )
		{
			$task_row[] = array_change_key_case($row, CASE_UPPER);
		}
		
		$template->assign('tasklist', $task_row);
		
		$db->sql_freeresult($result);
		return $db->sql_affectedrows($result);
		
	}
	
	/**
	 *	@purpose: Get a list of all users, and flag ones that are assigned to this task_id
	 *	@task_id: (int) task_id
	 *	@output: only display on page if output == true
	 */
	function get_task_userlist($task_id, $output = true)
	{
		global $db, $template;
		
		$task_users = $this->get_assigned_users('task', $task_id, false);
		$staff_users = $this->get_userlist($staff_only = true, $output = false);
		
		foreach( $staff_users as $user_id => $user_realname )
		{
			$task_user_row = array(
				'USER_ID'			=>	$user_id,
				'USER_REALNAME'		=>	$user_realname,
				'ASSIGNED'			=>	array_key_exists($user_id, $task_users) ? true : false,
			);
			
			$task_userlist[] = $task_user_row;
			unset($task_user_row);
		}
		
		if($output)
		{
			//overwrite get_userlist()
			$template->assign('userlist', $task_userlist);
		}
		
		return $task_userlist;
		
	}
	
	function get_project_userlist($project_id, $output = true)
	{
		global $db, $template;
		
		$project_users = $this->get_assigned_users('project', $project_id, false);
		$staff_users = $this->get_userlist($staff_only = true, false);

		foreach( $staff_users as $user_id => $user_realname )
		{
			$project_user_row = array(
				'USER_ID'			=>	$user_id,
				'USER_REALNAME'		=>	$user_realname,
				'ASSIGNED'			=>	array_key_exists($user_id, $project_users) ? true : false,
			);
			
			$project_userlist[] = $project_user_row;
			unset($project_user_row);
		}
		
		if($output)
		{
			//overwrite get_userlist()
			$template->assign('userlist', $project_userlist);
		}
		
		return $project_userlist;
	}
	
	/**
	 *	@purpose: Get all users assigned to a type_id
	 *	@type: task|project
	 *	@output: only display on page if output == true
	 */
	
	function get_assigned_users($type, $type_id, $output = true)
	//function get_assigned_users('task', $task_id, $output = true)
	{
		global $db, $template;

		$assigned_users = array();

		if( $type == 'task' )
		{
			$sql = "SELECT 
						t.user_id, t.task_id, u.user_realname, u.user_gender 
					FROM 
						".TASK_USERS_TABLE." t 
						JOIN ".USERS_TABLE." u ON u.user_id=t.user_id 
					WHERE 
						t.task_id=$type_id";
		}
		elseif( $type == 'project' )
		{
			$sql = "SELECT 
						DISTINCT p.user_id, p.project_id, u.user_realname, u.user_gender 
					FROM 
						".PROJECT_USERS_TABLE." p 
						JOIN ".USERS_TABLE." u ON u.user_id=p.user_id 
					WHERE 
						p.project_id=$type_id
						
					UNION ALL

					SELECT 
						DISTINCT tu.user_id, t.project_id, u.user_realname, u.user_gender 
					FROM 
						".TASKS_TABLE." t
						JOIN ".TASK_USERS_TABLE." tu ON tu.task_id=t.task_id
						JOIN ".USERS_TABLE." u ON u.user_id=tu.user_id
					WHERE
						t.project_id=$type_id";
		}
		else
		{
			return false;
		}

		$result = $db->sql_query($sql);
		while( $row = $db->sql_fetchrow($result) )
		{
			$gender_icon = ($row['user_gender'] == 'f' ? 'user-female' : 'user-male');
			
			$assigned_users[$row['user_id']] = get_user_val('user_realname', $row['user_id']);

			$user_row = array(
				'USER_ID'			=>	$row['user_id'],
				'USER_REALNAME'		=>	get_user_val('user_realname', $row['user_id']),
				'GENDER_ICO'		=>	$gender_icon,
			);
			
			$userlist[] = $user_row;
			unset($user_row);
		}
		
		if($output)
		{
			$template->assign('assigneduser', $userlist);
		}
		
		unset($userlist);
		$db->sql_freeresult($result);
		
		return $assigned_users;
	}

	/*
	* Display all messages for projects
	* that a user is assigned to.
	*/
	function mymessages($user_id, $project_id = false, $view_all = true)
	{
		global $db, $template, $config, $auth;
		
		$sql .= "SELECT DISTINCT message_id FROM (";
		
		if( $auth->user_group['S_STAFF'] )
		{
			$sql .= "SELECT m.message_id 
					FROM
						".MESSAGES_TABLE." m
						JOIN ".PROJECT_MESSAGES_TABLE." pm ON pm.message_id=m.message_id
						LEFT JOIN ".PROJECT_USERS_TABLE." pu ON pu.project_id=pm.project_id 
						LEFT JOIN ".PROJECTS_TABLE." p ON p.project_id=pm.project_id 
						LEFT JOIN ".TASKS_TABLE." t ON t.project_id=pm.project_id
						LEFT JOIN ".TASK_USERS_TABLE." tu ON tu.task_id=t.task_id 
					WHERE
						m.status=1
						AND ( (pu.user_id=$user_id OR tu.user_id=$user_id) OR m.user_id=$user_id )
						AND p.status = 1"
						. ($project_id ? " AND pm.project_id=$project_id " : '') . " "
						. ($view_all ? '' : " AND m.message_id NOT IN(SELECT message_id FROM ".READ_MESSAGES_TABLE.")");
		}
		else
		{
			$sql .= "SELECT m.message_id
					FROM 
						".MESSAGES_TABLE." m
						JOIN ".PROJECT_MESSAGES_TABLE." pm ON pm.message_id=m.message_id
						JOIN ".PROJECTS_TABLE." p ON p.project_id=pm.project_id
						JOIN ".CLIENT_USERS_TABLE." cu ON cu.client_id=p.client_id
					WHERE
						m.status=1
						AND (cu.user_id=$user_id OR m.user_id=$user_id)
						AND p.status = 1"
						. ($project_id ? " AND pm.project_id=$project_id " : '') . " "
						. ($view_all ? '' : " AND m.message_id NOT IN(SELECT message_id FROM ".READ_MESSAGES_TABLE.")");
		}
		
		$sql .= " UNION ALL
		
					SELECT m.message_id 
					FROM
						".MESSAGES_TABLE." m 
						JOIN ".USER_MESSAGES_TABLE." um ON um.message_id=m.message_id
					WHERE
						m.status=1
						AND (um.user_id=$user_id OR m.user_id=$user_id)"
						. ($view_all ? '' : " AND m.message_id NOT IN(SELECT message_id FROM ".READ_MESSAGES_TABLE.")");

		$sql .= ") messages";
		
		$result = $db->sql_query($sql);
		$message_count = $db->sql_affectedrows($result);
		
		//build list of message_ids
		while( $message_row = $db->sql_fetchrow($result) )
		{
			$this_message_id = (int) $message_row['message_id'];
			
			$row = $this->get_message_detail($this_message_id, false);

			$show_update = false;
			//was there a new reply or update to this message?
			if( !is_null($row['reply_date']) && $this->get_message_replies($row['message_id']) > 0 )
			{
				$show_update = true;
				$update_message = "New Reply: " . date($config['date_long'], $row['reply_date']);
			}
			
			if( !is_null($row['date_updated']) )
			{
				$show_update = true;
				$update_message .= "Updated: " . date($config['date_long'], $row['date_updated']);
			}
			
			$recipient_gender_ico = (get_user_val('user_gender', $row['recipient_user_id']) == 'f' ? 'user-female' : 'user-male');

			$default_avatar = (get_user_val('user_gender', $row['user_id']) == 'f' ? 'female.jpg' : 'male.jpg');
			$user_avatar = get_user_val('user_avatar', $row['user_id']);
			$avatar_image = ($user_avatar == '' ? $default_avatar : $user_avatar);
			
			$messagerow = array(
				'USER_REALNAME'		=>	$row['user_realname'],
				'USER_FIRSTNAME'	=>	$row['user_firstname'],
				'USER_ID'			=>	$row['user_id'],
				'USER_AVATAR'		=>	$avatar_image,
				
				'DATE_ADDED'		=>	date('h:i A', $row['date_added']),
				'DATE_UPDATED'		=>	date($config['date_long'], $row['date_updated']),
				'TIME_ADDED'		=>	date('h:i A', $row['date_added']),
				'POST_DATE'			=>	date($config['date_long'], ((isset($row['date_updated'])) ? $row['date_updated'] : $row['date_added']) ),
				'POST_TIME'			=>	date('h:i A', ((isset($row['date_updated'])) ? $row['date_updated'] : $row['date_added']) ),
				
				'MESSAGE_ID'		=>	$row['message_id'],
				'MESSAGE'			=>	litwicki_decode($row['message']),
				'SUBJECT'			=>	litwicki_decode($row['subject']),
				'SHOW_UPDATE'		=>	$show_update,
				'UPDATE_MESSAGE'	=>	$update_message,
				'REPLY_COUNT'		=>	$this->get_message_replies($row['message_id'], false),
				
				'PROJECT_ID'		=>	$row['project_id'],
				'PROJECT_NAME'		=>	litwicki_decode($row['project_name']),
				
				'RECIPIENT_USER_ID'		=>	$row['recipient_user_id'],
				'RECIPIENT_NAME'		=>	$row['recipient_name'],
				'RECIPIENT_GENDER_ICO'	=>	$recipient_gender_ico,
			);
			
			$messagerow_array[] = $messagerow;
			unset($messagerow);

			$update_message = "";
		}
		
		$template->assign('messagerow', $messagerow_array);
	
		unset($sql);
		unset($row);
		$db->sql_freeresult($result);
		
		return $message_count;

	}
	
	/*
	* Display all requests if staff
	* Display personal requests if client
	*/
	function myrequests($user_id, $show_hidden = false)
	{
		global $db, $template, $config, $auth;

		$rowcount = 1;

		
		
		$sql = "SELECT 
					*, r.status AS request_status 
				FROM 
					".REQUESTS_TABLE." r
					JOIN ".MESSAGES_TABLE." m ON r.message_id=m.message_id
					JOIN ".USERS_TABLE." u ON m.user_id=u.user_id
				WHERE 
					r.request_id NOT IN(SELECT request_id FROM ".PROPOSALS_TABLE." WHERE status=2) " 
					. (($show_hidden) ? " AND r.status <> 0" : " AND r.status IN(1,2)") 
					. " ORDER BY request_id DESC";

		$result = $db->sql_query($sql);
		$request_count = $db->sql_affectedrows($result);

		while( $row = $db->sql_fetchrow($result) )
		{
			//$phone_number = preg_replace("/(\d{3})(\d{3})(\d{4})/","($1) $2-$3",$row['user_phone']);
			$phone_number = parse_phone($row['user_phone']);
			
			$reply_count = $this->message_reply_count($row['message_id']);
			
			$requestrow = array(
				'DATE_ADDED'		=>	date($config['date_long'], $row['request_date']),
				'DATE_ADDED_SHORT'	=>	date($config['date_short'], $row['request_date']),
				'TIME_ADDED'		=>	date('h:i A', $row['request_date']),
				'DATETIME_ADDED'	=>	date($config['date_long'], $row['request_date']),
				'CLIENT_USER_ID'	=>	$row['user_id'],
				'MESSAGE_ID'		=>	$row['message_id'],
				'REQUEST_ID'		=>	$row['request_id'],
				'REPLIES'			=>	$reply_count,
				'COUNTER'			=>	$rowcount,
				'REQUEST_STATUS'	=>	$row['request_status'],
			);
			
			$requestrow_array[] = $requestrow;
			
			unset($requestrow);
			
			$rowcount++;
		}
		
		$template->assign('requestrow', $requestrow_array);

		unset($sql);
		unset($row);
		$db->sql_freeresult($result);

		return $request_count;

	}

	/*
	* Display all proposals if staff
	* Display personal proposals if client
	*/
	function myproposals($user_id, $show_hidden = false)
	{
		global $db, $template, $user, $config, $auth;
		
		$my_user_id = $user->data['user_id'];
		
		if( $auth->user_group['S_STAFF'] )
		{	
			$sql = "SELECT *
					FROM
						".MESSAGES_TABLE." m 
						JOIN ".PROPOSALS_TABLE." pp ON pp.message_id=m.message_id
						JOIN ".PROJECTS_TABLE." p ON p.project_id=pp.project_id
					ORDER BY
						pp.proposal_id DESC";
		}
		else
		{
			$sql = "SELECT * 
					FROM
						".MESSAGES_TABLE." m
						JOIN ".PROPOSALS_TABLE." pp ON pp.message_id=m.message_id
						JOIN ".PROJECTS_TABLE." p ON p.project_id=pp.project_id
						JOIN ".CLIENTS_TABLE." c ON c.client_id=p.client_id
						JOIN ".CLIENT_USERS_TABLE." cu ON cu.client_id=c.client_id
					WHERE
						cu.user_id = $user_id
					ORDER BY
						pp.proposal_id";
		}

		$result = $db->sql_query($sql);
		$proposal_count = $db->sql_affectedrows($result);

		while( $row = $db->sql_fetchrow($result) )
		{
			$project_id = (int) $row['project_id'];
		
			$project_users = $this->get_assigned_users('project', $project_id, false);
			
			$skip_proposal = false;
			
			if( $auth->user_group['S_STAFF'] && !in_array($user_id, $project_users) )
			{
				$skip_proposal = true;
			}
			
			if(!$skip_proposal)
			{
				$phone_number = parse_phone($row['user_phone']);
				
				$reply_count = $this->message_reply_count($row['message_id']);
				
				$proposalrow = array(
					'DATE_ADDED'		=>	date($config['date_short'], $row['date_added']),
					'DATE_UPDATED'		=>	date($config['date_long'], $row['date_updated']),
					'TIME_ADDED'		=>	date('h:i A', $row['date_added']),
					'POST_DATE'			=>	date($config['date_long'], ((isset($row['date_updated'])) ? $row['date_updated'] : $row['date_added']) ),
					'POST_TIME'			=>	date('h:i A', ((isset($row['date_updated'])) ? $row['date_updated'] : $row['date_added']) ),
					'MESSAGE_ID'		=>	$row['message_id'],
					'PROPOSAL_ID'		=>	$row['proposal_id'],
					'USER_ID'			=>	$row['user_id'],
					'SUBJECT'			=>	litwicki_decode($row['subject']),
					'PROJECT_ID'		=>	$row['project_id'],
					'PROJECT_NAME'		=>	litwicki_decode($row['project_name']),
					'PROPOSAL_TITLE'	=>	litwicki_decode($row['subject']),
					'AUTHOR_NAME'		=>	get_user_val("user_realname", $row['author_id']),
					'PROPOSAL_STATUS'	=>	$row['status'],
					'STATUS_DATE'		=>	date($config['date_long'], $row['status_date']),
					'REPLIES'			=>	$reply_count,
					'COUNTER'			=>	$rowcount,
					'ROW_CLASS'			=>	$row_class,
				);
				
				$proposalrow_array[] = $proposalrow;
				unset($proposalrow);
			}
			else
			{
				$proposal_count--;
			}
		}

		$template->assign('proposalrow', $proposalrow_array);
		
		unset($sql);
		unset($row);
		$db->sql_freeresult($result);

		return $proposal_count;

	}
	
	/*
	* Display all projects if staff
	* Display personal projects if client
	*/
	function myprojects($user_id, $show_hidden = false, $output = true, $client_id = false)
	{
		global $db, $template, $auth, $config, $user;

		$user_id = (int) $user->data['user_id'];
		
		//get list of project_ids user_id is manager for to use later
		$sql = "SELECT project_id FROM ".PROJECT_MANAGERS_TABLE." WHERE user_id=$user_id";
		$result = $db->sql_query($sql);
		while( $row = $db->sql_fetchrow($result) )
		{
			$manager[] = $row['project_id'];
		}
		
		$db->sql_freeresult($result);
		
		
		if($client_id)
		{
			$sql = "SELECT * FROM ".PROJECTS_TABLE." WHERE client_id=$client_id AND status=1";
		}
		else
		{
			if($auth->user_group['S_STAFF'])
			{
				if($auth->user_group['S_ADMINISTRATOR'] || $auth->user_group['S_MANAGER'])
				{
					$sql = "SELECT * 
							FROM 
								".PROJECTS_TABLE." p 
								JOIN ".CLIENTS_TABLE." c ON c.client_id=p.client_id 
							WHERE 
								p.project_id > 0";
				}
				else
				{
					$sql = "SELECT
								p.*, c.company , c.client_id 
							FROM 
								".PROJECTS_TABLE." p
								JOIN ".CLIENTS_TABLE." c ON c.client_id=p.client_id 
							WHERE 
								(p.project_id IN(SELECT t.project_id FROM ".TASKS_TABLE." t JOIN ".TASK_USERS_TABLE." tu ON t.task_id=tu.task_id WHERE tu.user_id=$user_id)
								OR p.user_id=$user_id)"; 
				}
			}
			else
			{
				$sql = "SELECT
							p.*, c.company, c.client_id 
						FROM 
							".PROJECTS_TABLE." p
							JOIN ".CLIENTS_TABLE." c ON c.client_id=p.client_id 
							JOIN ".CLIENT_USERS_TABLE." cu ON cu.client_id=c.client_id 
						WHERE
							cu.user_id=$user_id";
			}
			
			if($show_hidden)
			{
				$sql .= " AND p.status <> 0 AND p.project_id NOT IN(SELECT project_id FROM ".APPROVED_PROJECTS_TABLE.")";
			}
			else
			{
				$sql .= " AND p.status = 1";
			}
			
			$sql .= " ORDER BY p.project_id DESC, p.status_date DESC";
		}

		$result = $db->sql_query($sql);
		$project_count = $db->sql_affectedrows($result);

		while( $row = $db->sql_fetchrow($result) )
		{
			/**
			 *	For ADMINISTRATORs we check if he/she is assigned to any
			 *	task within this project_id. If so, then indicate in the myprojects
			 *	row for the user's viewing to make the display more usable.
			 */
			
			$task_assignment_count = $this->mytasks($user_id, $row['project_id'], false);
			
			//get number of tasks
			$project_id = $row['project_id'];
			$sql_ = "SELECT * FROM ".TASKS_TABLE." WHERE project_id=$project_id";
			$result_ = $db->sql_query($sql_);
			$task_count = $db->sql_affectedrows($result_);
			
			//avoid divide by zero if we don't have any tasks yet
			$task_count = $task_count == 0 ? 1 : $task_count;
			
			//let's include the project creation itself as an iteration so we don't ever have an ugly (empty) progress bar
			$completed_tasks = 1;
			
			//tally the tasks that are completed for a completion percentage
			while( $taskrow = $db->sql_fetchrow($result_) )
			{
				if( $taskrow['status'] == 2 )
				{
					$completed_tasks++;
				}
			}
			
			$completion_percentage = ($completed_tasks / $task_count) * 100;
			
			//make sure we're not 100% unless the project status is also complete
			if( $completion_percentage == 100 )
			{
				//if project status is not 2, and task_count is 1, we'll arbitrarily set completion to 50%
				if( $row['status'] != 2 && $task_count == 1 )
				{
					$completion_percentage = 10;
				}
			}
			
			$db->sql_freeresult($result_);
			
			//$phone_number = preg_replace("/(\d{3})(\d{3})(\d{4})/","($1) $2-$3",$row['user_phone']);
			$phone_number = parse_phone($row['user_phone']);
			
			$projectrow = array(
				'PROJECT_NAME'			=>	litwicki_decode($row['project_name']),
				'COMPANY_NAME'			=>	litwicki_decode($row['company']),
				'CLIENT_ID'				=>	$row['client_id'],
				'PROJECT_DESCRIPTION'	=>	litwicki_decode($row['project_description']),	
				'START_DATE'			=>	date($config['date_short'], $row['start_date']),
				'PROJECT_ID'			=>	$project_id,
				'COUNTER'				=>	$rowcount,
				'TASK_COUNT'			=>	$task_count,
				'TOTAL_HOURS'			=>	round($this->total_project_minutes($project_id) / 60, 2),
				'PROJECT_STATUS'		=>	$row['status'],
				'STATUS_DATE'			=>	date($config['date_long'], $row['status_date']),
				'ASSIGNED'				=>	$task_assignment_count > 0 ? true : false,
				'OWNER'					=>	$row['user_id'] == $user_id ? true : false,
				'MANAGER'				=>	in_array($project_id, $manager) ? true : false,
				'PROGRESS_VALUE'		=>	(int) $completion_percentage,
			);
			
			$projectrow_array[] = $projectrow;
			
			unset($projectrow);
		}
		
		if($output)
		{
			$template->assign('projectrow', $projectrow_array);
		}
		
		unset($sql);
		unset($row);
		$db->sql_freeresult($result);

		return $project_count;

	}
	
	function myclients($output = true)
	{
		$clientlist = $this->get_clientlist($project_id = false, $user_id = false, $output);
		return count($clientlist);
	}
	
	function mytasks($user_id, $project_id = false, $output = true)
	{
		global $db, $template, $auth, $config;

		if( $auth->user_group['S_ADMINISTRATOR'] || $auth->user_group['S_MANAGER'] )
		{
			$sql = "SELECT 
						*, t.status as task_status 
					FROM 
						".TASKS_TABLE." t 
						JOIN ".PROJECTS_TABLE." p ON p.project_id=t.project_id
					WHERE p.status = 1";
		}
		else
		{
			if( !$auth->user_group['S_STAFF'] )
			{
				$sql = "SELECT 
						*, t.status as task_status 
					FROM 
						".TASKS_TABLE." t
						JOIN ".PROJECTS_TABLE." p ON p.project_id=t.project_id
						JOIN ".CLIENTS_TABLE." c ON c.client_id=p.client_id
						JOIN ".CLIENT_USERS_TABLE." cu ON cu.client_id=c.client_id
					WHERE
						p.status = 1
						AND cu.user_id=$user_id";
			}
			else
			{	
				$sql = "SELECT 
						*, t.status as task_status 
					FROM 
						".TASKS_TABLE." t
						JOIN ".TASK_USERS_TABLE." tu ON tu.task_id=t.task_id
						JOIN ".PROJECTS_TABLE." p ON p.project_id=t.project_id
					WHERE
						p.status = 1
						AND tu.user_id=$user_id";
			}
		}
		
		if($project_id)
		{
			$sql .= " AND t.project_id=$project_id AND t.status <> 0 ";
		}
		else
		{
			$sql .= " AND t.status IN(1,3) AND t.task_id NOT IN(SELECT t.task_id FROM ".APPROVED_TASKS_TABLE.")";
		}
		
		$sql .= " ORDER BY t.task_id DESC";

		$result = $db->sql_query($sql);
		$task_count = $db->sql_affectedrows($result);

		while( $row = $db->sql_fetchrow($result) )
		{
			$task_users = $this->get_assigned_users('task', $row['task_id'], false);

			//$phone_number = preg_replace("/(\d{3})(\d{3})(\d{4})/","($1) $2-$3",$row['user_phone']);
			$phone_number = parse_phone($row['user_phone']);
			
			$status_date = date($config['date_long'], ((!is_null($row['status_date'])) ? $row['status_date'] : $row['start_date']));
			
			$taskrow = array(
				'TASK_NAME'			=>	litwicki_decode($row['task_name']),
				'TASK_STATUS'		=>	litwicki_decode($row['task_status']),
				'TASK_DESCRIPTION'	=>	litwicki_decode($row['task_description']),
				'START_DATE'		=>	date($config['date_short'], $row['start_date']),
				'DUE_DATE'			=>	date($config['date_short'], $row['due_date']),
				'STATUS_DATE'		=>	$status_date,
				'PROJECT_ID'		=>	$row['project_id'],
				'PROJECT_NAME'		=>	litwicki_decode($row['project_name']),
				'TASK_ID'			=>	$row['task_id'],
				'COUNTER'			=>	$rowcount,
				'TASK_HOURS'		=>	round($this->task_minutes($row['task_id']) / 60, 2),
				'ASSIGNED'			=>	array_key_exists($user_id, $task_users) ? true : false, // for admins
			);
			
			$taskrow_array[] = $taskrow;
			
			unset($taskrow);
			
			$rowcount++;
		}
		
		//don't print tasks to page when a project_id is specified
		if($output)
		{
			$template->assign('taskrow', $taskrow_array);
		}
		
		unset($sql);
		unset($row);
		$db->sql_freeresult($result);

		return $task_count;

	}
	
	//Get message_id for request then use $this->get_message_detail()
	function get_request_detail($request_id, $output = true)
	{
		global $db;

		//get the message_id for this request
		$sql = "SELECT message_id FROM ".REQUESTS_TABLE." WHERE request_id=$request_id";
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrow($result);
		$message_id = $row['message_id'];

		if($message_id)
		{
			//get all the details for this message
			$row = $this->get_message_detail($message_id, $output);
			return $row;
		}

		return false;

	}
	
	/*
	* Get all replies for a message not "deleted" or "approved"
	* where in this case, an "approved" message is closed so
	* it does not display on the message list unless viewed from
	* within the details of a project.
	*/
	function get_message_replies($message_id, $output = true)
	{
		global $db, $template, $config;

		$message_id = (int) $message_id;
		
		$sql = "SELECT * 
				FROM 
					".REPLIES_TABLE." 
				WHERE 
					parent_id=$message_id 
					AND status=1 
				ORDER BY
					reply_id DESC";
					
		$result = $db->sql_query($sql);
		$reply_count = $db->sql_affectedrows($result);

		while( $row = $db->sql_fetchrow($result) )
		{
			//return the message row without outputting the data with $template
			$replyrow = $this->get_message_detail($row['message_id'], false);

			$reply_array = array(
				'SUBJECT'			=>	$replyrow['subject'],
				'MESSAGE'			=>	$replyrow['message'],
				'USER_REALNAME'		=>	$replyrow['user_realname'],
				'DATE_ADDED'		=>	date($config['date_long'], $replyrow['date_added']),
				'MESSAGE_ID'		=>	$row['message_id'],
				'REPLY_ID'			=>	$row['reply_id'],
			);
			
			$replyrow_array[] = $reply_array;
			unset($reply_array);
		}
		
		if($output)
		{
			$template->assign('replyrow', $replyrow_array);
		}
		
		unset($row);
		$db->sql_freeresult($result);

		return $reply_count;
	}

	function message_reply_count($message_id)
	{
		global $db;

		$sql = "SELECT * FROM ".REPLIES_TABLE." WHERE parent_id=$message_id AND status=1";
		$result = $db->sql_query($sql);
		$reply_count = $db->sql_affectedrows($result);
		return $reply_count;
	}

	//Approve a proposal, close the associated request and email the staff a link to the approved proposal.
	function approve_proposal($proposal_id)
	{
		global $db, $base_url, $user, $admin_user_id;
		
		$client_user_id = $user->data['user_id'];
		$user_realname = $user->data['user_realname'];
		
		//update proposal to approved
		$this->change_status('proposal', $proposal_id, $this->approve_status);

		$sql = "SELECT 
					p.request_id, p.project_id	
				FROM 
					".PROPOSALS_TABLE." p 
					JOIN ".MESSAGES_TABLE." m ON m.message_id=p.message_id
				WHERE 
					p.proposal_id=$proposal_id";
					
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrow($result);
		$request_id = $row['request_id'];
		$project_id = $row['project_id'];
		
		//approve this request
		$this->change_status('request', $request_id, $this->approve_status);
		
		$assigned_user = get_user_val("user_realname", $client_user_id);
	
		//activate the founding project
		$this->change_status('project', $project_id, $this->open_status);
		
		$proposal_row = $this->get_proposal_detail($proposal_id);
		
		$proposal_name = $proposal_row['subject'];
		
		$proposal_link = '<a href="'.$base_url.'/proposals.php?id='.$proposal_id.'">'.$proposal_name.'</a>';
		
		$log_details = "$user_realname approved proposal: $proposal_link.";

		$this->dashboard_log($client_user_id, $client_user_id, $this->timestamp, $proposal_link);
		
		//let's also log this for staff
		foreach(stafflist() as $staffname => $staff_user_id)
		{
			$this->dashboard_log($client_user_id, $staff_user_id, $this->timestamp, "Staff Alert: " . $log_details);
		}
		
		return false;

	}
	
	function decline_proposal($proposal_id)
	{
		$this->change_status('proposal', $proposal_id, $this->delete_status);
		
		$proposal_row = $this->get_proposal_detail($proposal_id, false);
		$request_id = $proposal_row['request_id'];
		
		//reopen the initial request
		$this->change_status('request', $request_id, $this->open_status);
		
		return true;
	}

	function dashboard_email($subject, $message, $client_id, $html_email = false, $priority = 3, $attachments = false)
	{
		global $db, $config;
		
		$client_id = (int) $client_id;
		
		//for a given client_id get all the users and notify them
		$sql = "SELECT user_id FROM ".CLIENT_USERS_TABLE." cu JOIN ".CLIENTS_TABLE." c ON c.client_id=cu.client_id WHERE c.client_id=$client_id AND cu.email=1";
		$result = $db->sql_query($sql);

		while( $row = $db->sql_fetchrow($result) )
		{
			$user_id = (int) $row['user_id'];
			email_user($subject, $message, $user_id, $html_email, $priority, $attachments);
		}
		
		return true;
	}

	//Get the table name, primary key, and misc for a particular 'type'
	function item_type($type)
	{
		global $base_url;

		if(!$type)
		{
			return false;
		}

		$item = array();

		switch ($type)
		{
			case 'attachment':
				
				$item["table_name"] 	= ATTACHMENTS_TABLE;
				$item["users_table"] 	= '';
				$item["primary_key"] 	= "attachment_id";
				$item["email_subject"] 	= "Litwicki Media - New File";
				$item["querystring"]	= '';
				break;
				
			case 'project':

				$item["table_name"] 	= PROJECTS_TABLE;
				$item["users_table"] 	= PROJECT_USERS_TABLE;
				$item["primary_key"] 	= "project_id";
				$item["email_subject"] 	= "Litwicki Media - New Project";
				$item["querystring"]	= "projects.php?id=";
				break;

			case 'task':

				$item["table_name"] 	= TASKS_TABLE;
				$item["users_table"]	= TASK_USERS_TABLE;
				$item["primary_key"] 	= "task_id";
				$item["email_subject"] 	= "Litwicki Media - New Task";
				$item["querystring"]	= "tasks.php?id=";
				break;
			
			case 'task_timelog':

				$item["table_name"] 	= TASK_TIMELOG_TABLE;
				$item["users_table"]	= '';
				$item["primary_key"] 	= "task_log_id";
				$item["email_subject"] 	= "Litwicki Media - Time Logged";
				$item["querystring"]	= "timelog.php?id=";
				break;

			case 'message':

				$item["table_name"] 	= MESSAGES_TABLE;
				$item["users_table"] 	= '';
				$item["primary_key"] 	= "message_id";
				$item["email_subject"] 	= "Litwicki Media - New Message";
				$item["querystring"]	= "messages.php?id=";
				break;

			case 'request':

				$item["table_name"] 	= REQUESTS_TABLE;
				$item["users_table"] 	= '';
				$item["primary_key"] 	= "request_id";
				$item["email_subject"] 	= "Litwicki Media - New Proposal Request";
				$item["querystring"]	= "requests.php?id=";
				break;

			case 'proposal':

				$item["table_name"] 	= PROPOSALS_TABLE;
				$item["users_table"] 	= '';
				$item["primary_key"] 	= "proposal_id";
				$item["email_subject"] 	= "Litwicki Media - New Project Proposal!";
				$item["querystring"]	= "proposals.php?id=";
				break;
				
			case 'reply':
			
				$item["table_name"] 	= REPLIES_TABLE;
				$item["users_table"] 	= '';
				$item["primary_key"] 	= "reply_id";
				$item["email_subject"] 	= "Litwicki Media - New Message Response!";
				$item["querystring"]	= "reply.php?id=";
				break;

		}

		$item["email_message"]	= "A new $type has been added to your dashboard: $base_url/" . $item['querystring'];

		return $item;

	}

	function dashboard_log($user_id, $owner_id, $log_date, $log_details)
	{
		global $db;
		
		$log_row = array(
			'user_id'	=>	$user_id,
			'owner_id'	=>	$owner_id,
			'log_date'	=>	$log_date,
			'log_details'	=>	sanitize($log_details),
		);
		
		$sql = 'INSERT INTO '.LOG_TABLE.' ' . $db->sql_build_array('INSERT', $log_row);
		$db->sql_query($sql);
		
		return true;
	}
	
	function clear_log($user_id)
	{
		global $db;
		if(is_staff($user_id))
		{
			$sql = "DELETE FROM ".LOG_TABLE;
			$db->sql_query($sql);
			return true;
		}
		else
		{
			return false;
		}
	}

	function show_log($user_id, $num_of_records = 50)
	{
		global $db, $template, $config;

		$sql = "SELECT * FROM ".LOG_TABLE." ORDER BY log_date DESC";
		
		if($num_of_records)
		{
			$sql .= " LIMIT 0,$num_of_records";
		}

		$result = $db->sql_query($sql);
		
		$log_count = $db->sql_affectedrows($result);

		if($log_count)
		{
			while( $row = $db->sql_fetchrow($result) )
			{
				$logrow = array(
					'LOG_DATE'		=>	date($config['date_long'], $row['log_date']),
					'LOG_DETAILS'	=>	litwicki_decode($row['log_details']),
				);
				
				$logrow_array[] = $logrow;
				unset($logrow);
			}
			
			$template->assign('logrow', $logrow_array);
			
			return true;
		}
		
		return false;
		
	}
	
	function rate_detail($rate_id)
	{
		global $db;
		
		$rate_id = (int) $rate_id;
		
		if(!$rate_id)
		{
			return false;
		}
		
		$sql = "SELECT * FROM ".RATES_TABLE." WHERE rate_id=$rate_id";
		$result = $db->sql_query($sql);
		$rate_row = $db->sql_fetchrow($result);
		
		return $rate_row;
	}
}
?>
