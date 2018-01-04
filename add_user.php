<?php

include '../bootstrap.php';
include 'controller.php';

require_login('task');

$task_id = param('id');

if (!$task_id) error('No task id passed to view!');

$task = load_tasks(['ids'=>$task_id]);

// get a map from user ids to permissions
$project_user_permissions = request('project','user_list',['id'=>$task['project_id']]);
$project_user_ids = array_keys($project_user_permissions);
$project_users = request('user','list',['ids'=>implode(',', $project_user_ids)]);
load_users($task,$project_users);

foreach ($task['users'] as $uid => $task_user) unset($project_users[$uid]);

$title = $task['name'].' - Umbrella';

$allowed = isset($task['users'][$user->id]['permissions'])
           && in_array($task['users'][$user->id]['permissions'], [TASK_PERMISSION_OWNER,TASK_PERMISSION_PARTICIPANT]);

if ($allowed){
	if (empty($project_users)) {
		warn('All members of this project are already assigned to this task.');
		redirect('view');
	}
	
	if ($task_user = post('task_user')){
		add_user_to_task($task_id,$task_user,post('permissions'));
		redirect('view');
	}
} else error('You are not allowed to edit the user list of this task!');

include '../common_templates/head.php';
include '../common_templates/main_menu.php';
include '../common_templates/messages.php'; 

if ($allowed){ ?>
<h1>Add user to <?= $task['name']?></h1>
<form method="POST">
	<fieldset><legend>Add user to task</legend>
		<fieldset>
			<select name="task_user">
				<option value="" selected="true">= Select a user =</option>
				<?php foreach ($project_users as $id => $u){
					if (in_array($id, $project_users_ids)) continue; ?>
				<option value="<?= $id ?>"><?= $u['login']?></option>
				<?php }?>
			</select>
			<label>
			<input type="checkbox" name="permissions" value="<?= TASK_PERMISSION_PARTICIPANT ?>" checked="true">Participant
			</label>	
		</fieldset>
		<input type="submit" />
	</fieldset>
</form>
<?php }
include '../common_templates/closure.php'; ?>
