<?php include 'controller.php';

require_login('model');

$process_id = param('id');
if (empty($process_id)) {
	error('No model id passed to view!');
	redirect(getUrl('model'));
}

$process = Process::load(['ids'=>$process_id])->loadProject();

$action = param('action');
if ($action == 'delete' && param('confirm')=='true'){
	Process::delete($process->id);
	redirect(getUrl('model','?project='.$process->project_id));
}

include '../common_templates/head.php';

include '../common_templates/main_menu.php';
include '../common_templates/messages.php';

if ($action == 'delete'){?>
	<fieldset>
		<legend><?= t('Delete "?"',$process->name)?></legend>
		<?= t('You are about to delete the model "?". Are you sure you want to proceed?',$process->name) ?>
		<a class="button" href="?action=delete&confirm=true"><?= t('Yes')?></a>
		<a class="button" href="?"><?= t('No')?></a>
	</fieldset>
<?php } ?>

<table class="vertical model" style="width: 100%">
	<tr>
		<th><?= t(empty($process->r)?'Model':'Process')?></th>
		<td>
			<h1><?= $process->name ?></h1>
			<span class="symbol">
				<a title="<?= t('edit')?>"	href="edit"></a>
				<a title="<?= t('export process') ?>" href="export"></a>
				<a title="<?= t('delete process')?>" href="?action=delete"></a>
			</span>
		</td>
	</tr>
	<tr>
		<th><?= t('Project')?></th>
		<td class="project">
			<a href="<?= getUrl('project',$process->project['id'].'/view'); ?>"><?= $process->project['name']?></a>
			&nbsp;&nbsp;&nbsp;&nbsp;
			<a href="<?= getUrl('files').'?path=project/'.$process->project['id'] ?>" class="symbol" title="<?= t('show project files'); ?>" target="_blank"></a>
			<a class="symbol" title="<?= t('show other models') ?>"   href="<?= getUrl('model').'?project='.$process->project['id'] ?>"></a>
			</td>
	</tr>
	<?php if ($process->description){ ?>
	<tr>
		<th><?= t('Description')?></th>
		<td class="description"><?= markdown($process->description) ?></td>
	</tr>
	<?php } ?>
		<tr>
		<th><?= t('Display') ?>
			<div class="symbol">
				<a title="<?= t('add terminal')?>" href="<?= getUrl('model','add_terminal_to_process/'.$process->id) ?>"></a>
				<a title="<?= t('add process')?>"  href="<?= getUrl('model','add_subprocess_to/'.$process->id) ?>"></a>
				<?php if (!$process->isModel()) { ?>
				<a title="<?= t('add connector')?>" href="<?= getUrl('model','add_connector_to_process/'.$process->id) ?>"></a><?php } ?>
			</div>
		</th>
		<td>
			<div id="contextmenu" style="position: absolute; display: inline-block;">
				<button class="delete">Delete</button>
			</div>
			<svg
				 viewbox="0 0 1000 1000"
				 onload="initSVG(evt)"
				 onmousedown="grab(evt)"
				 onmousemove="drag(evt)"
				 onmouseup="drop(evt)"
				 onwheel="wheel(evt)"
				 oncontextmenu="menu(evt)"
				 context="<?= $process->id ?>">
				<script xlink:href="<?= getUrl('model','model.js')?>"></script>
				<script type="text/javascript">
					var model_base = '<?= getUrl('model')?>';
					var flow_prompt = '<?= t('Set name for new flow:'); ?>'
					var no_name_set = '<?= t('No name given. Will not create flow.'); ?>'
					hideContextMenu();
				</script>

				<rect id='backdrop' x='-10%' y='-10%' width='110%' height='110%' pointer-events='all' />
				<?php $process->svg(); ?>
			</svg>
		</td>
	</tr>

	<?php
	$shown = [];
	if ($process->terminals()){ ?>
	<tr>
		<th><?= t('Terminals')?></th>
		<td class="terminals">
		<?php foreach ($process->terminals() as $terminal){ if ($terminal->type == Terminal::DATABASE || in_array($terminal->name,$shown)) continue; ?>
		<a class="button" href="terminal/<?= $terminal->id ?>" title="<?= $terminal->description?>"><?= $terminal->name ?></a>
		<?php $shown[] = $terminal->name; } ?>
		</td>
	</tr>
	<tr>
		<th><?= t('Databases')?></th>
		<td class="databases">
		<?php foreach ($process->terminals() as $terminal){ if ($terminal->type == Terminal::TERMINAL || in_array($terminal->name,$shown)) continue; ?>
		<a class="button" href="terminal/<?= $terminal->id ?>" title="<?= $terminal->description?>"><?= $terminal->name ?></a>
		<?php $shown[] = $terminal->name; } ?>
		</td>
	</tr>
	<?php }
	$shown = [];
	if ($process->children()){ ?>
	<tr>
		<th><?= t('Processes')?></th>
		<td class="processes">
		<?php foreach ($process->children() as $child){ if (in_array($child->id,$shown)) continue;?>
		<a class="button" href="<?= getUrl('model',$child->id.'/view') ?>" title="<?= $child->description ?>"><?= $child->name ?></a>
		<?php $shown[] = $child->id; } ?>
		</td>
	</tr>
	<?php } ?>

	<?php if (isset($services['notes'])) {
		$notes = request('notes','html',['uri'=>'model:'.$process->id],false,NO_CONVERSION);
		if ($notes){ ?>
	<tr>
		<th><?= t('Notes')?></th>
		<td><?= $notes ?></td>
	</tr>
	<?php }}?>
</table>
<?php include '../common_templates/messages.php'; ?>
<?php include '../common_templates/closure.php';