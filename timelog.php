<?php 

/****
 *
 *  @author: jake@litwickimedia.com
 *  @SVN: $Id: timelog.php 32 2010-05-24 20:20:02Z jake $
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

if( isset($_POST['addtime']) )
{
	$task_id = (int) $_POST['task_id'];
	$work_description =litwicki_cleanstr($_POST['description']);
	$username = $user->data['user_realname'];
	$work_date = strtotime($_POST['work_date']);
	$work_log_date = time();
	$work_hours = (int) ($_POST['hours_worked'] * 60);
	$work_type_id = (int) $_POST['rate_id'];
	
	$timelog_row = array(
		'task_id'			=>	$task_id,
		'user_id'			=>	(int) $user_id,
		'date_added'		=>	$work_log_date,
		'minutes'			=>	$work_hours,
		'work_description'	=>	$work_description,
		'work_date'			=>	$work_date,
		'rate_id'			=>	$work_type_id,
	);
	
	$short_description = strlen($work_description) > 97 ? strip_tags(substr($work_description, 0, 100)) . '...' : $work_description;

	//add the timelog
	$time_log_id = $dashboard->add_task_timelog($timelog_row, $send_email = true);
	
	$htmlrow = '<tr>'
				.'<td class="icon"></td>'
				.'<td class="name">'.$username.'</td>'
				.'<td class="date">'.date($config['date_short'], $work_date).'</td>'
				.'<td class="date" style="width: auto;">'.date('m/d h:i A', $work_log_date).'</td>'
				.'<td class="num">'.($work_hours / 60).'</td>'
				.'<td class="icon">'.$short_description.'</td>'
				.'</tr>';

	$task_hours = round($dashboard->task_minutes($task_id) / 60, 2);
	$task_hours_label = $task_hours > 0 ? "There are $task_hours hours logged for this task." : "There are 0 hours logged for this task.";

	$json_array = array(
		'timelogrow'	=>	$htmlrow,
		'task_hours'	=>	$task_hours_label,
	);
	
	$json = json_encode($json_array);
	echo $json;
	
	exit;
	
}
elseif( isset($_POST['removetime']) )
{
	$task_id = (int) $_POST['task_id'];
	$task_log_id = (int) $_POST['task_log_id'];
	$dashboard->change_status('task_timelog', $task_log_id, $closed_status);
	
	$task_hours = round($dashboard->task_minutes($task_id) / 60, 2);
	$task_hours_label = $task_hours > 0 ? "There are $task_hours hours logged for this task." : "There are no hours logged for this task.";

	$json_array = array(
		'task_log_id'	=>	$task_log_id,
		'task_hours'	=>	$task_hours_label,
	);
	
	$json = json_encode($json_array);
	echo $json;
	
	exit;
	
}
else
{
	if( isset($_GET['id']) )
	{
		$task_log_id = (int) $_GET['id'];
		$sql = "SELECT task_id FROM ".TASK_TIMELOG_TABLE." WHERE task_log_id=$task_log_id";
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrow($result);
		
		$task_id = $row['task_id'];
		
		if( $task_id === 0 )
		{
			redirect("$base_url/");
		}
		else
		{
			redirect("$base_url/tasks.php?id=$task_id");
		}
	}
	else
	{
		redirect("$base_url/");
	}
}

?>