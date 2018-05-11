<?php $title = 'Umbrella Model Management';

include '../bootstrap.php';
include 'controller.php';

require_login('model');

if ($model_id = param('id')){
	$model = Model::load(['ids'=>$model_id]);
} else {
	error('No model id passed!');
	redirect(getUrl('model'));
}

$action = param('action');
if ($action == 'delete' && param('confirm')=='true'){
	$model->delete(); die();	
	redirect(getUrl('model','?project='.$model->project_id));
}

info('This Module is not functional, yet.');
include '../common_templates/head.php';

include '../common_templates/main_menu.php';
include '../common_templates/messages.php'; 

if ($action == 'delete'){?>
	<fieldset>
		<legend><?= t('Delete "?"',$model->name)?></legend>
		<?= t('You are about to delete the model "?". Are you sure you want to proceed?',$model->name) ?>
		<a class="button" href="?action=delete&confirm=true"><?= t('Yes')?></a>
		<a class="button" href="?"><?= t('No')?></a>
	</fieldset>
<?php } ?>

<table class="vertical model" style="width: 100%">
	<tr>
		<th><?= t('Model')?></th>
		<td>
			<h1><?= $model->name ?></h1>
			<span class="right symbol">
				<a title="<?= t('edit')?>"	href="edit"></a>
				<a title="<?= t('add terminal')?>" href="add_terminal"></a>
				<a title="<?= t('add process')?>" href="add_process"></a>
				<a title="<?= t('delete model')?>" href="?action=delete"></a>
			</span>
		</td>
	</tr>
	<tr>
		<th><?= t('Project')?></th>
		<td class="project">
			<a href="<?= getUrl('project',$model->project_id.'/view'); ?>"><?= $model->project['name']?></a>
			&nbsp;&nbsp;&nbsp;&nbsp;
			<a href="<?= getUrl('files').'?path=project/'.$model->project_id ?>" class="symbol" title="<?= t('show project files'); ?>" target="_blank"></a>
			</td>
	</tr>
	<?php if ($model->description){ ?>
	<tr>
		<th><?= t('Description')?></th>
		<td class="description"><?= $model->description; ?></td>
	</tr>
	<?php } ?>
	<?php if ($model->terminals()){ ?>
	<tr>
		<th><?= t('Terminals')?></th>
		<td class="terminals">
		<?php foreach ($model->terminals() as $terminal){ if ($terminal->type) continue; ?>
		<a class="button" href="terminal/<?= $terminal->id ?>" title="<?= $terminal->description?>"><?= $terminal->id ?></a> 
		<?php } ?>
		</td>
	</tr>
	<tr>
		<th><?= t('Databases')?></th>
		<td class="databases">
		<?php foreach ($model->terminals() as $terminal){ if (!$terminal->type) continue;?>
		<a class="button" href="terminal/<?= $terminal->id ?>" title="<?= $terminal->description ?>"><?= $terminal->id ?></a>
		<?php } ?>
		</td>
	</tr>
	<?php } ?>
	<?php if ($model->processes()){ ?>
	<tr>
		<th><?= t('Processes')?></th>
		<td class="processes">
		<?php foreach ($model->processes() as $process){ ?>
		<a class="button" href="process/<?= $process->id ?>" title="<?= $process->description ?>"><?= $process->id ?></a> 
		<?php } ?>
		</td>
	</tr>
	<?php } ?>
	<tr>
		<th><?= t('Display') ?></th>
		<td>
			<svg
				 viewbox="0 0 1000 1000"
				 onload="initSVG(evt)"
				 onmousedown="grab(evt)"
				 onmousemove="drag(evt)"
				 onmouseup="drop(evt)"
				 onwheel="wheel(evt)">
				<script xlink:href="<?= getUrl('model','model.js')?>"></script>
				<rect id='backdrop' x='-10%' y='-10%' width='110%' height='110%' pointer-events='all' />

				<?php foreach ($model->process_instances() as $process){
					$process->svg($model);
				} // foreach process

 				foreach ($model->terminal_instances() as $term){
 					$term->svg();
 				} // foreach terminal?>
			</svg>
		</td>
	</tr>
</table>
<?php include '../common_templates/closure.php';