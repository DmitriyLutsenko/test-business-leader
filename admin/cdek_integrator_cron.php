<?php
// Version
define('VERSION', '3.0.2.0');

// Configuration
if (is_file('config.php')) {
	require_once('config.php');
}

// Install
if (!defined('DIR_APPLICATION')) {
	header('Location: ../install/index.php');
	exit;
}


$_SERVER['SERVER_PORT'] = 443;
// Startup
require_once(DIR_SYSTEM . 'startup.php');

start('cdekcron');