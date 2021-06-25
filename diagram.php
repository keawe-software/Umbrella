<?php include 'controller.php';

require_login('model');

$base_url = getUrl('model');

$diagram_id = param('id');
if (empty($diagram_id)) {
	error('No diagram id passed!');
	redirect($base_url);
}

$diagram = Diagram::load(['ids'=>$diagram_id]);
$project = $diagram->project();
if (empty($project)){
	error('You are not allowed to access this diagram!');
	redirect($base_url);
}

$action = param('action');
if ($action == 'delete' && param('confirm')=='true'){
	Diagram::delete($diagram->id);
	redirect($base_url.'?project='.$diagram->project_id);
}

include '../common_templates/head.php';

include '../common_templates/main_menu.php';
include '../common_templates/messages.php';

if ($action == 'delete'){?>
	<fieldset>
		<legend><?= t('Delete "◊"',$diagram->name)?></legend>
		<?= t('You are about to delete the diagram "◊". Are you sure you want to proceed?',$diagram->name) ?>
		<a class="button" href="?action=delete&confirm=true"><?= t('Yes')?></a>
		<a class="button" href="?"><?= t('No')?></a>
	</fieldset>
<?php } ?>

<table>
	<tr>
		<th><?= t('Diagram')?></th>
		<td>
			<h1><?= htmlspecialchars($diagram->name) ?></h1>
			<span class="hover_h symbol">
				<a href="<?= $base_url.'edit_diagram/'.$diagram_id ?>" title="<?= t('Edit diagram')?>"></a>
			</span>
		</td>
	</tr>
	<tr>
		<th><?= t('Project')?></th>
		<td>
			<a href="<?= getUrl('project',$project['id'].'/view')?>"><?= htmlspecialchars($project['name']) ?></a>
			&nbsp;&nbsp;
			<a href="<?= $base_url.'?project='.$project['id'] ?>"><span class="symbol"></span> <?= t('Models')?></a>
		</td>
	</tr>
	<tr>
		<th><?= t('Description')?></th>
		<td><?= markdown($diagram->description) ?></td>
	</tr>
	<tr>
		<th><?= t('Display')?></th>
		<td>
			<?php $party_count = 0; ?>
			<table class="diagram">
				<tr>
					<td class="phase"><?= t('Phases')?><br/><br/></td>
					<?php foreach ($diagram->parties() as $party_id => $party) { $party_count++; ?>
					<th colspan="2" title="<?= htmlspecialchars($party->description) ?>">
						<?php if ($party->position > 0) { ?><a class="symbol" href="<?= $base_url.'move_party_left/'.$party_id?>"></a><?php } ?>
						<?= htmlspecialchars($party->name)?>
					</th>
					<?php } ?>
					<td>
						<a class="button" href="<?= getUrl('model','add_party_to_diagram/'.$diagram_id.'?position='.$party_count) ?>" title="<?= t('Add a new party to this diagram')?>"><?= t('add party')?></a>
					</td>
				</tr>

				<?php foreach ($diagram->phases() as $phase_id => $phase) { ?>
				<tr>
					<td class="phase" rowspan="<?= count($phase->steps()) +2 ?>" id="phase<?= $phase_id ?>">
						<a class="button" href="<?= getUrl('model','add_phase_to_diagram/'.$diagram_id.'?position='.$phase->position) ?>" title="<?= t('Add a new phase to this diagram')?>"><?= t('add phase')?></a>
						<p>
						<?= markdown($phase->description) ?>
						</p>
					</td>
				</tr>
				<tr>
					<td class="phase" colspan="<?= $party_count*2 ?>">
						<h4>
							<?= htmlspecialchars($phase->name) ?>
							<a class="symbol" href="<?= $base_url.'edit_phase/'.$phase_id ?>"></a>
							<a class="symbol" href="<?= $base_url.'delete_phase/'.$phase_id ?>"></a>
						</h4>
					</td>
					<td class="actions">
						<a class="button" href="<?= getUrl('model','add_step_to_phase/'.$phase_id.'?position=0') ?>" title="<?= t('Add a new step to this phase')?>"><?= t('add step')?></a>
					</td>
				</tr>
				<?php foreach ($phase->steps() as $step_id => $step) { $count = 0; $first=null; $last=0; $left = true; ?>
				<tr>
					<?php foreach ($diagram->parties() as $party_id => $party) {
						if ($step->source == $party_id || $step->destination == $party_id) {
							if ($first === null) {
								$first = $count;
								if ($step->source == $party_id) $left = false;
							}
							$last = $count;
						}
						$count++;
					}

					$length = 1+$last-$first;
					$post_length = $count-$last-1;

					if ($length>1){ ?>
						<td colspan="<?= 2*$first+1 ?>"></td>
						<td colspan="<?= 2*$length-2 ?>" class="step" id="step<?= $step->id ?>">
							<?= htmlspecialchars($step->name) ?>
							<a class="symbol" href="<?= $base_url.'edit_step/'.$step_id ?>"></a>
							<a class="symbol" href="<?= $base_url.'delete_step/'.$step_id ?>"></a>
							<a class="symbol" href="<?= $base_url.'step_up/'.$step_id ?>"></a>
							<div class="arrow" style="text-align: <?= $left?'left':'right'?>">
								<img src="/common_templates/img/a<?= $left?'l':'r'?>.gif"/>
							</div>
							<div class="content"><?= markdown($step->description) ?></div>
						</td>
						<td colspan="<?= 2*$post_length+1?>"></td>
					<?php } else { // length == 1, i.e. "one column"
						if ($first > 0){ // empty cols before?><td colspan="<?= 2*$first ?>"></td><?php } ?>
						<td colspan="2" class="step" id="step<?= $step->id ?>">
							<?= htmlspecialchars($step->name) ?>
							<a class="symbol" href="<?= $base_url.'edit_step/'.$step_id ?>"></a>
							<a class="symbol" href="<?= $base_url.'delete_step/'.$step_id ?>"></a>
							<div class="content"><?= markdown($step->description) ?></div>
						</td>
						<?php if ($post_length > 0){ // empty cols after ?><td colspan="<?= 2*$post_length ?>"></td><?php } ?>
					<?php } ?>
					<td class="actions">
						<a class="button" href="<?= getUrl('model','add_step_to_phase/'.$phase_id.'?position='.($step->position+1)) ?>" title="<?= t('Add a new step to this phase')?>"><?= t('add step')?></a>
					</td>
				</tr>
				<?php }?>
				<?php }?>
				<tr>
					<td>
						<a class="button" href="<?= getUrl('model','add_phase_to_diagram/'.$diagram_id.'?position='.($phase->position+1)) ?>" title="<?= t('Add a new phase to this diagram')?>"><?= t('add phase')?></a>
					</td>
					<td colspan="<?= 2*$party_count +1 ?>"></td>
				</tr>
			</table>
		</td>
	</tr>
	<?php if (isset($services['notes'])) { ?>
	<tr>
		<th><?= t('Notes')?></th>
		<td><?= request('notes','html',['uri'=>'model:diagram:'.$diagram->id,'context'=>t('Diagram "◊"',$diagram->name),'users'=>array_keys($project['users'])],false,NO_CONVERSION) ?></td>
	</tr>
	<?php } ?>
</table>

<?php include '../common_templates/messages.php'; ?>
<?php include '../common_templates/closure.php';