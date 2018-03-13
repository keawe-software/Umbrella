<?php

include '../bootstrap.php';
include 'controller.php';

require_login('bookmark');

if ($key = param('key')){
	$result = search_bookmarks($key);
	$tags = $result['tags'];
	$urls = $result['urls'];
	
	$url = getUrl('bookmark');
	foreach ($tags as $tag){ ?>
	<a class="button" href="<?= $url.$tag.'/view' ?>"><?= $tag ?></a>
	<?php } 
	foreach ($urls as $hash => $link ) {?>
	<fieldset>
		<legend>
			<a class="symbol" href="<?= $url.$hash ?>/edit?returnTo=<?= urlencode(location('*'))?>"></a>
			<a class="symbol" href="<?= $url.$hash ?>/delete?returnTo=<?= urlencode(location('*'))?>"></a>
			<a <?= $link['external']?'target="_blank"':''?> href="<?= $link['url'] ?>" ><?= isset($link['comment']) ? $link['comment']:$link['url']?></a>
		</legend>
		<a <?= $link['external']?'target="_blank"':''?> href="<?= $link['url'] ?>" ><?= $link['url'] ?></a>
		<?php if (isset($link['tags'])) { ?>
		<div class="tags">		
			<?php foreach ($link['tags'] as $related){ ?>
			<a class="button" href="<?= getUrl('bookmark',$related.'/view') ?>"><?= $related ?></a>
			<?php } ?>
		</div>
		<?php } ?>
		<fieldset class="share">
			<legend><?= t('share')?></legend>
			<form method="POST">
				<input type="hidden" name="share_url_hash" value="<?= $hash?>" />
				<select name="share_user_id">
				<option value=""><?= t('select user')?></option>
				<?php foreach ($users as $uid => $some_user) {
					if ($uid == $user->id) continue; ?>
				<option value="<?= $uid?>"><?= $some_user['login'] ?></option>
				<?php } ?>
				</select>
				<input type="submit" />
			</form>
		</fieldset>
	</fieldset>
	<?php } ?>
<?php }