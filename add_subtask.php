<?php include 'controller.php';
require_login('task');

if ($task_id = param('id')){
	$task = Task::load(['ids'=>$task_id]);
	$new_task = new Task();
	if (post('name')){
		$user_permissions = post('users');
		if (is_array($user_permissions) && !empty($user_permissions)){
			$users = [];
			foreach ($task->project('users') as $uid => $entry){
				$u = $entry['data'];
				$perm = ($uid == $user->id) ? TASK_PERMISSION_OWNER : $user_permissions[$uid];
				if ($perm == 0) continue;
				$u['permission'] = $perm;
				$users[$uid] = $u;
			}

			$new_task->patch($_POST)->patch(['users'=>$users,'project_id'=>$task->project_id,'parent_task_id'=>$task_id])->save();
			redirect(getUrl('task',$task_id.'/view'));
		} else error('Selection of at least one user is required!');
	}
} else error('No parent task id passed!');

if (!$task->is_writable()) {
	error('You are not allowed to add sub-tasks to this task!');
	redirect('view');
}

include '../common_templates/head.php';
include '../common_templates/main_menu.php';
include '../common_templates/messages.php'; ?>
<form method="POST">
	<fieldset>
		<fieldset>
			<legend><?= t('Project')?></legend>
			<a href="<?= getUrl('project',$task->project_id.'/view')?>" ><?= $task->project('name')?></a>
			&nbsp;&nbsp;&nbsp;&nbsp;
			<a href="<?= getUrl('files').'?path=project/'.$task->project_id ?>" class="symbol" title="show project files" target="_blank"></a>
		</fieldset>
		<legend><?= t('Add subtask to "?"','<a href="'.getUrl('task',$task->id.'/view').'">'.$task->name.'</a>') ?></legend>
		<fieldset><legend><?= t('Name')?></legend>
			<input type="text" name="name" value="<?= $new_task->name ?>" autofocus="true"/>
		</fieldset>
		<fieldset>
			<legend><?= t('Description - <a target="_blank" href="?">Markdown supported ↗cheat sheet</a>',t('MARKDOWN_HELP'))?></legend>
			<textarea name="description"><?= $new_task->description; ?></textarea>
		</fieldset>
		<fieldset>
			<legend><?= t('Estimated time')?></legend>
			<label>
				<?= t('? hours','<input type="number" name="est_time" />')?>
			</label>
		</fieldset>
		<fieldset>
			<legend><?= t('Permissions') ?></legend>
			<table>
				<tr>
					<th><?= t('User')?></th>
					<th title="<?= t('read + write')?>" class="symbol"></th>
					<th title="<?= t('read only')?>" class="symbol"></th>
					<th title="<?= t('no access')?>" class="symbol"></th>
				</tr>
			<?php foreach ($task->project('users') as $id => $u) { $owner = $id == $user->id; ?>
				<tr>
					<td><?= $u['data']['login']?></td>
					<td><input type="radio" name="users[<?= $id ?>]" title="<?= t('read + write')?>" value="<?= TASK_PERMISSION_READ_WRITE ?>" <?= $owner?'checked="checked"':'' ?>/></td>
					<td><input type="radio" name="users[<?= $id ?>]" title="<?= t('read only')?>"    value="<?= TASK_PERMISSION_READ ?>" /></td>
					<td><input type="radio" name="users[<?= $id ?>]" title="<?= t('no access')?>"    value="0" <?= $owner?'':'checked="checked"' ?>/></td>
				</tr>
			<?php } ?>
			</table>
			<label>
				<input type="checkbox" name="notify" checked="true" />
				<?= t('notify users') ?>
			</label>
			<p>
			<?= t('Only selected users will be able to access the task!') ?>
			</p>
		</fieldset>
		<?php if (isset($services['bookmark'])){?>
		<fieldset><legend><?= t('Tags')?></legend>
			<input name="tags" type="text" value="<?= param('tags') ?>" />
		</fieldset>
		<?php }?>
		<fieldset>
			<legend><?= t('Start date')?></legend>
			<input name="start_date" type="date" value="<?= date('Y-m-d');?>" pattern="[0-9]{4}-[0-9]{2}-[0-9]{2}" />
		</fieldset>
	        <fieldset>
			<legend><?= t('Due date')?></legend>
			<input name="due_date" type="date" value="<?= $task->due_date ?>" pattern="[0-9]{4}-[0-9]{2}-[0-9]{2}" />
		</fieldset>
		<button type="submit"><?= t('add subtask'); ?></button>
	</fieldset>
</form>

<?php include '../common_templates/closure.php'; ?>