<?php include 'controller.php';

require_login('stock');

if ($item_id = param('id')){
	$parts = explode(':', $item_id);
	$realm = $parts[0];
	$realm_id = $parts[1];
	switch ($realm){
		case 'company':
			$company = request($realm,'json',['ids'=>$realm_id]);
			assert(!empty($company),t('You are not allowed to access items of this ?',$realm));
			break;
		case 'user':
			assert($realm_id == $user->id,t('You are not allowed to access items of this ?',$realm));
			break;
	}
	$item = Item::load(['ids'=>$item_id]);
	$num = array_pop($parts);
	$prefix = implode(':', $parts);
	if (!$item) redirect($base_url.$prefix.DS.'add');
} else error('No item id supplied!');

$index_url = $base_url.$prefix.DS.'index';

include '../common_templates/head.php';
include '../common_templates/main_menu.php';
include 'menu.php';
include '../common_templates/messages.php'; ?>

<fieldset>
	<legend><?= $item->name ?></legend>
<table class="stock">
	<tr>
		<th><a href="<?= $base_url.$prefix.':'.($num-1).DS.'view' ?>">&lt;</a> <?= t('ID')?> <a href="<?= $base_url.$prefix.':'.($num+1).DS.'view' ?>">&gt;</a></th>
		<th><?= t('Code')?></th>
		<td colspan="2">
			<a class="button" href="<?= $index_url ?>"><?= t('Stock index')?></a>
			<a class="button" href="<?= getUrl('files','?path='.str_replace(':', DS,$prefix).DS.'stock'.DS.'item:'.$num)?>"><?= t('Files')?></a>
			<a class="button" href="<?= getUrl('stock',$item_id.'/add_property'.($company_id?'?company='.$company_id:''))?>"><?= t('Add property')?></a>
		</td>
	</tr>
	<tr>
		<th colspan="2"><?= t('Location')?></th>
		<th colspan="2"><?= t('Properties')?></th>
	</tr>
	
	<?php  
		$properties = $item->properties();
		$prop = empty($properties) ? null : array_shift($properties); ?>
	<tr class="first">
		<td><?= $item_id ?></td>
		<td><?= $item->code ?></td>
		<td>
			<?= empty($prop)?'':$prop->name() ?>
		</td>
		<td>
			<?= empty($prop)?'':$prop->value ?>&nbsp;<?= empty($prop)?'':$prop->unit() ?>
		</td>
	</tr>
	<?php $first = true; while (!empty($properties)) { 
	$prop = array_shift($properties); ?>
	<tr>
		<td colspan="2">
			<?php if ($first) { ?>
			<a href="<?= getUrl('stock',$item->id.'/alter_location'.($company_id?'?company='.$company_id:'')) ?>"><?= $item->location()->full() ?></a>
			<?php } ?>
		</td>
		<td>
			<?= empty($prop)?'':$prop->name() ?>
		</td>
		<td>
			<?= empty($prop)?'':$prop->value ?>&nbsp;<?= empty($prop)?'':$prop->unit() ?>
		</td>
	</tr>
	<?php $first = false; } // while properties not empty ?>
	
</table>
</fieldset>
<?php if (isset($services['notes'])) echo request('notes','html',['uri'=>'stock:'.$item->id],false,NO_CONVERSION); 

include '../common_templates/closure.php'; ?>
