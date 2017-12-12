<?php $title = 'Umbrella Project Management';

include '../bootstrap.php';
include 'controller.php';

require_login('project');
$project_id = param('id');
if (!$project_id) error('No project id passed to view!');


if ($name = post('name')){
	update_project($project_id,$name,post('description'),post('company'));
	if ($redirect=param('redirect')){
		redirect($redirect);
	} else {
		redirect('view');
	}
}

$project = load_projects(['ids'=>$project_id,'single'=>true]);
$companies = request('company','json_list');

include '../common_templates/head.php'; 
include '../common_templates/main_menu.php';
include 'menu.php';
include '../common_templates/messages.php'; ?>
<form method="POST">
	<fieldset>
		<legend><?= t('Edit Project')?></legend>
                <fieldset>
                        <legend>Company</legend>
                        <select name="company">
				<option value="0"><?= t('== no company assigned =='); ?></option>
                        <?php foreach($companies as $company) { ?>
                                <option value="<?= $company['id'] ?>" <?= $company['id'] == $project['company_id']?'selected="true"':''?>><?= $company['name'] ?></a>
                        <?php } ?>
                        </select>
                </fieldset>
		<fieldset>
			<legend><?= t('Name')?></legend>
			<input type="text" name="name" value="<?= $project['name']; ?>"/>
		</fieldset>
		<fieldset>
			<legend><?= t('Description')?></legend>
			<textarea name="description"><?= $project['description']?></textarea>
		</fieldset>
		<input type="submit" />
	</fieldset>
</form>

<?php include '../common_templates/closure.php'; ?>
