<?php include 'controller.php';

require_login('files');

$filename = param('file');

if (access_granted($filename)){
	$user_id = param('user_id');
	$unshare_user = param('unshare');
	if ($user_id !== null) share_file($filename,$user_id,post('send_mail'));
	if ($unshare_user !== null) unshare_file($filename,$unshare_user);

	$shares = array_keys(get_shares($filename));
} else {
	error('You are not allowed to access "◊".',$filename);
}

$users = load_connected_users();

include '../common_templates/head.php';
include '../common_templates/main_menu.php';
include 'menu.php';
include '../common_templates/messages.php';

if (isset($shares)){ ?>

<fieldset>
	<legend><?= t('File shares of file ◊',$filename)?></legend>
	<table>
		<tr>
			<th><?= t('User'); ?></th>
			<th><?= t('Actions') ?></th>
		</tr>
		<?php foreach ($shares as $user_id) { ?>
		<tr>
			<td><?= $user_id == 0 ? t('Guest') : $users[$user_id]['login']?></td>
			<td>
				<a class="symbol" title="<?= t('cancel sharing') ?>" href="<?= location() ?>&unshare=<?= $user_id ?>"></a>
			</td>
		</tr>
		<?php } ?>
	</table>
	<br/>
	<form method="POST">
	<fieldset>
		<legend><?= t('add user')?></legend>
		<select name="user_id">
		<option value=""><?= t('select user')?></option>
		<option value="0"><?= t('Guest'); ?></option>
		<?php foreach ($users as $uid => $some_user) {
			if ($uid == $user->id) continue;
			if (in_array($uid, $shares)) continue;
			?>
		<option value="<?= $uid ?>"><?= $some_user['login'] ?></option>
		<?php } ?>
		</select>
		<p>
			<label>
				<input type="checkbox" name="send_mail" checked="true" />
				<?= t('Send notification email to user.') ?>
			</label>
		</p>
		<button type="submit"><?= t('Share file')?></button>
	</fieldset>
	</form>
</fieldset>

<?php } // if shares

include '../common_templates/closure.php'; ?>
