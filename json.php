<?php include 'controller.php';

require_login('time');

$options = [];
if ($ids = param('ids'))$options['ids'] = explode(',',$ids);
if ($task_ids = param('task_ids')) $options['task_ids'] = $task_ids;
if ($ids_only = param('ids_only')) $options['ids_only'] = $ids_only;

$times = Timetrack::load($options);
foreach ($times as &$time) unset($time->tasks);

die(json_encode($times));
