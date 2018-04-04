<?php $title = 'Umbrella Task Management';

include '../bootstrap.php';
include 'controller.php';
require_login('task');

$project_id = param('id');
if (!$project_id) error('No project id passed!');

$name = post('name');
$description = post('description');
$user_ids = param('users');

$project = request('project','json',['ids'=>$project_id,'users'=>'true']);
$project_users = request('user','json',['ids'=>array_keys($project['users'])]);

if ($name){
	if (is_array($user_ids) && !empty($user_ids)){
		$users = array_intersect_key($project_users, array_flip($user_ids));
		$task = add_task($name,$description,$project_id,null, post('start_date'), post('due_date'),$users);
		redirect(getUrl('task',$task['id'].'/view'));
	} else error('Selection of at least one user is required!');
}


include '../common_templates/head.php'; 
include '../common_templates/main_menu.php';
include '../common_templates/messages.php'; ?>
<form method="POST">
	<fieldset><legend><?= t('Create new task')?></legend>
		<fieldset>
			<legend><?= t('Project')?></legend>
			<a href="<?= getUrl('project',$project_id.'/view')?>" ><?= $project['name']?></a>
			&nbsp;&nbsp;&nbsp;&nbsp;
			<a href="<?= getUrl('files').'?path=project/'.$project_id ?>" class="symbol" title="show project files" target="_blank"></a>
		</fieldset>
		<fieldset><legend><?= t('Name')?></legend>
			<input type="text" name="name" value="<?= $name ?>" autofocus="true"/>
		</fieldset>
		<fieldset>
			<legend><?= t('Description - <a target="_blank" href="?">Markdown supported ↗cheat sheet</a>',t('MARKDOWN_HELP'))?></legend>
			<textarea name="description"><?= $description; ?></textarea>
		</fieldset>
		<fieldset>
			<legend><?= t('Estimated time')?></legend>
			<label>
				<?= t('? hours','<input type="number" name="est_time" />')?>
			</label>
		</fieldset>
		<fieldset>
			<legend><?= t('Users') ?></legend>
			<select name="users[]" multiple="true">
			<?php foreach ($project_users as $id => $u){ ?>
				<option value="<?= $id ?>" <?= ($id == $user->id)?'selected="true"':''?>><?= $u['login'] ?></option>
			<?php } ?>
			</select>
			<?= t('Only selected users will be able to access the task!') ?>
		</fieldset>
		<?php if (isset($services['bookmark'])){?>
		<fieldset><legend><?= t('Tags')?></legend>
			<input name="tags" type="text" value="<?= param('tags') ?>" />
		</fieldset>
		<?php }?>
		<fieldset>
			<legend><?= t('Start date')?></legend>
			<input name="start_date" type="date" pattern="[0-9]{4}-[0-9]{2}-[0-9]{2}" value="<?= date('Y-m-d');?>"/>
		</fieldset>
		<fieldset>
			<legend><?= t('Due date')?></legend>
			<input name="due_date" type="date" pattern="[0-9]{4}-[0-9]{2}-[0-9]{2}" />
		</fieldset>
		<button type="submit"><?= t('Save task') ?></button>
	</fieldset>
</form>

<?php include '../common_templates/closure.php'; ?>
