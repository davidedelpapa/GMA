<?php
/*
 * Debug Code
 */
/*
ini_set('display_errors', 'On');
error_reporting(E_ALL);
*/

/*
 * configuration
 */
$config = parse_ini_file("config.ini", true);

/* ----------------------------------------------------- */
/*                      SERVER SETUP                     */
/* ----------------------------------------------------- */

$db_connection = new mysqli($config['mysql']['host'], $config['mysql']['user'], $config['mysql']['password'], $config['mysql']['database'], $config['mysql']['port']);
if ($db_connection->connect_error) {
    error_log('MySql Connection failed: ' . $db_connection->connect_error);
    http_response_code(500);
    die(1);
}

try {
	$redis = new Redis();
	$redis->connect($config['redis']['host'], $config['redis']['port']);
}
catch (Exception $e) {
	error_log('Redis connection failed: ' . $e->getMessage());
    http_response_code(500);
    die(1);
}

/* ----------------------------------------------------- */
/*                     KEY  CONTROL                      */
/* ----------------------------------------------------- */
/* 
 * Do not serve the tile without an Api Key
 * In case of trying to access without a key, returns a HTTP 403 error code.
 */
if(isset( $_REQUEST['key'])){
    $key = $db_connection->real_escape_string($_REQUEST['key']);
} else {
    http_response_code(403);
    die(1);
}

/*
 * Allowing tiles info storing only with a proper key, i.e., one attached to a registered user. 
 * In case of trying to access without a proper key, returns a HTTP 403 error code.
 */

if ( !$db_connection->query("SELECT Users.userid FROM Users INNER JOIN pkeys ON Users.userid = pkeys.userid WHERE pkeys.pkey = '" . $key . "';") ) {
    http_response_code(403);
    die(1);
}

/* ----------------------------------------------------- */
/*                SESSION  CONTROL (REDIS)               */
/* ----------------------------------------------------- */

// Get session expiring time from config.ini
$session_max_inactivity_time = $config['session']['max-inactivity'];

// Set a session_id
$uid = md5($_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT']);
$timestamp = date('mdyHis');
if ($redis->exists($uid)){
    $current_session = $redis->get($uid);
    $redis->expire($uid, $session_max_inactivity_time);
}
else {
    $current_session = $timestamp . $uid;
    $redis->setnx($uid, $current_session);
    $redis->expire($uid, $session_max_inactivity_time);
}

/* ----------------------------------------------------- */
/*                    SERVER ROUTINES                    */
/* ----------------------------------------------------- */
    
/* Close MySql Connection */
$db_connection->close();

/* Store Tile and Session Info */
try {
    $redis->rpush('pywk', '>SessionRecords ' . $current_session . ' ' . $key . ' ' .  $_SERVER['REMOTE_ADDR'] . ' ' .  $timestamp  . ' ' . $_REQUEST['z'] . ' ' . $_REQUEST['x'] . ' ' . $_REQUEST['y']);
}
catch (Exception $e) {
	error_log('Redis tile information storing failed: ' . $e->getMessage());
    http_response_code(500);
    die(1);
}
http_response_code(200);
die();
?>