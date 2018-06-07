<?php $title = 'Umbrella Notes Management';

include '../bootstrap.php';
include 'controller.php';

require_login('notes');

$options = ['ids' => param('id')];

if ($order = param('order')) $options['order'] = $order;
if (($limit = param('limit')) !== null) $options['limit'] = $limit;

$note = Note::load($options);

if ($new_code = param('code')){
	$note->patch(['note'=>$new_code]);
	$note->save();
	redirect($note->url());
}



if (file_exists('../lib/parsedown/Parsedown.php')){
	include '../lib/parsedown/Parsedown.php';
	$parsedown  = Parsedown::instance();
}

include '../common_templates/head.php';

include '../common_templates/main_menu.php';
include '../common_templates/messages.php';

?>
<table class="note">
	<tr>
		<th><?= t('usage') ?></th>
		<th><?= t('rendered output') ?></th>
		<th><?= t('code') ?></th>
	</tr>
	<tr>
		<td><a href="<?= $note->url() ?>"><?= $note->uri ?></a></td>
		<td><?= $parsedown?$parsedown->parse($note->note):str_replace("\n", "<br/>", $note->note) ?></td>
		<td class="code">
			<form method="POST">
				<textarea name="code"><?= $note->note ?></textarea>
				<button type="submit"><?= t('Save') ?></button>
			</form>
		</td>
	</tr>
</table>
<?php include '../common_templates/closure.php';