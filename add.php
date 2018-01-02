<?php $title = 'Umbrella File Management';

include '../bootstrap.php';
include 'controller.php';

require_login('files');

$dir = param('dir');
if (access_granted($dir)){
	if (isset($_FILES['file'])){
		$file_info = $_FILES['file'];
		if ($file_info['size'] == 0) {
			error('Uploaded file is empty!');
		} else {
			$info = add_file($file_info);
			if (is_array($info)) {
				if (isset($services['bookmark'])){ // add to bookmarks
					$tags = explode(DS, $dir);
					array_splice($tags, array_search('user'.$user->id, $tags ), 1); // delete "userXX" from tags
					$tags[] = t('File');
					$tags[] = $info['name'];
					$display_url = getUrl('files','index?path='.$info['dir']);
					$tags=implode(' ', $tags);
				 	request('bookmark','add',['url'=>$display_url,'tags'=>$tags,'comment'=>t('Show "?" in Umbrella File Manager.',$info['name'])]);
				}
				redirect('index'.($dir?'?path='.$dir:''));
			} else error($info);
		}    	
	}
} else {
	error('You are not allowed to add files to "?"',$dir);
}
include '../common_templates/head.php'; 
include '../common_templates/main_menu.php';
include 'menu.php';
include '../common_templates/messages.php'; ?>
<form method="POST" enctype="multipart/form-data">
	<fieldset>
		<legend><?= t('Upload new file'); ?></legend>
		<input type="file" name="file" />
		<button type="submit"><?= t('Save') ?></button>
	</fieldset>
</form>

<?php include '../common_templates/closure.php'; ?>
