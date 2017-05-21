<?php

include 'config.php';

assert_options(ASSERT_ACTIVE,   true);
assert_options(ASSERT_BAIL,     false);
assert_options(ASSERT_WARNING,  true);
assert_options(ASSERT_CALLBACK, 'assert_failure');

$errors = array();
$infos = array();

function assert_failure($script, $line, $message){
	error('Assertion failed in '.$script.', line '.$line.': '.$message);
	include 'common_templates/messages.php';
	include 'common_templates/closure.php';
        die();
}

function getUrl($service,$path){
	global $services,$token;
	return $services[$service].$path.'?token='.$token;
}

function request($service,$path,$show_request = false){
	$url = getUrl($service,$path);
	if ($show_request) echo $url.'<br/>';
	$response = file_get_contents($url);
	return json_decode($response,true);
}

function post($name){
	if (isset($_POST[$name])) return $_POST[$name];
	return null;
}

function info($message){
	global $infos;
	$infos[] = $message;
}
function error($message){
	global $errors;
	$errors[] = $message;
}

function debug($object,$die = false){
	echo '<pre>'.print_r($object,true).'</pre>';
	if ($die){
		include 'common_templates/closure.php';
		die();
	}
}

$token = null;
if (isset($_COOKIE['UmbrellaToken'])) $token = $_COOKIE['UmbrellaToken'];
