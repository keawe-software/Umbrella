<a class="symbol" title="<?= t('add user')?>" href="add"></a>
<a class="symbol" title="<?= t('connect with other account')?>" href="add_openid_login"></a>
<?php if (isset($user->id)) { ?>
<a class="symbol" title="<?= t('edit your account')?>" href="<?= $user->id ?>/edit"></a>
<?php } ?>