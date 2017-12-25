<?php $title = 'Umbrella Files';

include '../bootstrap.php';
include 'controller.php';

require_login('files');
$path = param('path','user'.$user->id);
$entries = list_entries($path);
$parent = dirname($path);

if (param('format') == 'json'){
	die(json_encode($entries));
}

include '../common_templates/head.php'; 
include '../common_templates/main_menu.php';
include 'menu.php';
include '../common_templates/messages.php'; ?>

<fieldset>
	<legend><?= t('Files: ?',$path?$path:' ')?></legend>
<table>
	<tr>
		<th><?= t('File / Directory') ?></th>
		<th><?= t('Actions') ?></th>
	</tr>
	<?php if (!in_array($parent,['.',''])){ ?>
	<tr>
		<td>
			<a title="<?= t('move one directory up') ?>" href="?path=<?= $parent ?>">
				<span class="symbol"></span> ..
			</a>
		</td>
		<td></td>
	</tr>
	<?php } ?>
	<?php foreach ($entries['dirs'] as $dir){ ?>
	<tr>
		<td>
			<a href="?path=<?= $path.DS.$dir ?>">
				<span class="symbol"></span> <?= $dir ?>
			</a>
		</td>
		<td>
			<a class="symbol" title="<?= t('rename') ?>" href="rename?file=<?= $path.DS.$dir ?>"></a>
			<a class="symbol" title="<?= t('delete')?>"  href="delete?file=<?= $path.DS.$dir ?>"></a>			
		</td>
	</tr>
	<?php }?>
	<?php foreach ($entries['files'] as $file){ 
		$filename = urlencode($path.DS.$file);	?>
	<tr>
		<td>
			<a title="download" href="download?file=<?= $filename ?>">
				<span class="symbol"></span> <?= $file ?>
			</a>
		</td>
		<td>
			<a class="symbol" title=<?= t('share')?> href="share?file=<?= $filename ?>"></a>
			<a class="symbol" title="<?= t('rename') ?>" href="rename?file=<?= $filename ?>"></a>
			<a class="symbol" title="<?= t('delete')?>" href="delete?file=<?= $filename ?>"></a>
		</td>
	</tr>
	<?php }?>
	<tr>
		<td>
			<a href="shared">
				<span class="symbol"></span> <?= t('shared files')?>
			</a>
		</td>
		<td></td>
	</tr>
	
</table>
</fieldset>
<?php include '../common_templates/closure.php'; ?>
