<?php include 'controller.php';

require_login('document');
$options = [];
if ($times = param('times')) $options['times'] = $times;

die(json_encode(Document::load($options)));