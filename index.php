<?php $title = 'Umbrella Task Management';

include '../bootstrap.php';
include 'controller.php';

require_login('task');
$selection = [];
if ($order = param('order')) $selection['order'] = $order;
$tasks = get_tasks($selection);
//debug($tasks,true);
$projects = request('project','list');
$show_closed = param('closed') == 'show';

include '../common_templates/head.php';
include '../common_templates/main_menu.php';
include '../common_templates/messages.php'; ?>

<div class="hover right-fix">
	<table>
		<tr><th>
			<?= t('Hide/Show') ?> (<a href="#" onclick="return toggle('[class^=project_]');"><?= t('all')?></a>)
		</th></tr>
		<?php foreach ($projects as $pid => $project){ ?>
		<tr class="plist">
			<td>
			<a href="#" onclick="return toggle('.project_<?= $pid ?>');"><?= $project['name']; ?></a>
			</td>
		</tr>
		<?php } ?>
	</table>
</div>
<table class="tasklist">
	<tr>
		<th><a href="?order=name"><?= t('Name')?></a></th>
		<th><a href="?order=parent_task_id"><?= t('Parent Task')?></a></th>
		<th><a href="?order=project_id"><?= t('Project')?></a></th>
		<th><a href="?order=status"><?= t('Status')?></a></th>
		<th><a href="?order=start_date"><?= t('Start')?></a></th>
		<th><a href="?order=due_date"><?= t('Due')?></a></th>
		<th>Actions</th>
	</tr>

<?php 
	$hide = [];
	foreach ($tasks as $id => $task){ // filter out tasks, that are only group nodes
		if ($task['status'] >= 60 && !$show_closed) continue;
		if (isset($task['parent_task_id'])) $hide[] = $task['parent_task_id'];
	}
	foreach ($tasks as $id => $task):
	if ($task['status'] >= 60 && !$show_closed) continue;
	if (in_array($id, $hide)) continue;
	$project = $projects[$task['project_id']];
	$parent_id = $task['parent_task_id'];
	?>
	<tr class="project_<?= $task['project_id']?>">
		<td><a href="<?= $id ?>/view"><?= $task['name'] ?></a></td>
		<td>
			<?php if ($parent_id !== null && isset($tasks[$parent_id])) { ?>
			<a href="../task/<?= $parent_id ?>/view"><?= $tasks[$parent_id]['name'] ?></a>
			<?php } ?>
		</td>
		<td><a href="../project/<?= $task['project_id']?>/view"><?= $project['name'] ?></a></td>
		<td><?= t($TASK_STATES[$task['status']]) ?></td>
		<td><?= $task['start_date'] ?></td>
		<td><?= $task['due_date'] ?></td>
		<td>
			<a title="<?= t('edit')?>"     href="<?= $id ?>/edit?redirect=../index"     class="symbol"></a>
			<a title="<?= t('start')?>  "  href="<?= $id ?>/start?redirect=../index"    class="<?= $task['status'] == TASK_STATUS_STARTED  ? 'hidden':'symbol'?>"></a>
			<a title="<?= t('complete')?>" href="<?= $id ?>/complete?redirect=../index" class="<?= $task['status'] == TASK_STATUS_COMPLETE ? 'hidden':'symbol'?>"></a>
			<a title="<?= t('cancel')?>"   href="<?= $id ?>/cancel?redirect=../index"   class="<?= $task['status'] == TASK_STATUS_CANCELED ? 'hidden':'symbol'?>"></a>
			<a title="<?= t('do open')?>"     href="<?= $id ?>/open?redirect=../index"     class="<?= $task['status'] == TASK_STATUS_OPEN     ? 'hidden':'symbol'?>"></a>
			<a title="<?= t('wait')?>"     href="<?= $id ?>/wait?redirect=../index"	  class="<?= $task['status'] == TASK_STATUS_PENDING  ? 'hidden':'symbol'?>"></a>

		</td>
	</tr>
<?php endforeach; ?>

</table>
<?php
include '../common_templates/closure.php'; ?>
