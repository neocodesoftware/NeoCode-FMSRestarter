<?php

// restart.php - restart FileMaker and generate status page
//
// Vers. 1.0 , YP 10/09/2015
//
// Restart remote FileMaker server.
// Receive login/pass from user, check md5 checksum with stored in ini file and restart remote FileMaker server
// Call php restart.php --action=ACTION --user=FM_SERVER_UserName --pass=FM_SERVER_Pass --status=STASUS_FILE.txt
//
// Copyright © 2015 Neo Code Software Ltd
// Artisanal FileMaker & PHP hosting and development since 2002
// 1-888-748-0668 http://store.neocodesoftware.com
//
// History:
// ALPHA, YP 04/20/2015 - Alpha release
// 1.0 , YP 10/09/2015 - Initial release
//


include('Net/SSH2.php');				// Pure-PHP implementation of SSHv2
include('Crypt/RSA.php');				// For public key authorization
include_once('subs.php');			// common subs
include_once('class.Log.php');		// Log class
include_once('class.CheckStart.php');
									// Global vars
$INIFILE = 'restart.ini';
$INVALID_PASS_SLEEP = 2;			// Sleep time in case of invalid password
$MAX_START_TRIES = 10;				// Number of attempts to start server before we give up
$START_TRIE_TIMEOUT = 5;			// Timeout between start server attempts
$STAT_LIFETIME = 3600;				// Stat file lifetime in seconds


									// Start here
set_error_handler("error_handler", E_ALL); // Catch all error/notice messages

if (!file_exists($INIFILE)) { 		// Check if config file exists
  die("Config file not found");
}
									// Load config
//$config = parse_ini_file($INIFILE, true,INI_SCANNER_RAW); // INI_SCANNER_RAW works only in php 5.3
$config = parse_ini_file($INIFILE, true); 
									// Replace DOUBLEQUOTES string with '"'. Can't have '"' in ini file in php <5.3
foreach($config['main'] as $key => $val) {
  if ($key != 'DQ') {
    $config['main'][$key] = str_replace($config['main']['DQ'],'"',$config['main'][$key]);
  }
}

if ($error = checkConfig($config)) {// Check required parameters from config file
  die($error);
}
//var_dump($config);

$log = new LOG($config['main']['log_dir'].'restart.log');		// Log object
$ckStart = new CheckStart($config['main']['log_dir'].'restart.lock');
if(!$ckStart->canStart()) {			// Check if script already running. Doesn't allow customer to send multiple restart requests
  printLogAndDie("Script is already running.");
}

//$options = getopt('',array ('user:','pass:','status:'));	// Read input Works in php 5.3 and above
//var_dump($options);
foreach($argv as $v) {				// Read input works in php < 5.3
  if(false !== strpos($v, '=')) {
	$parts = explode('=', $v);
	if (strpos($parts[0],'--')===0) {
	  $options[substr($parts[0],2)] = $parts[1];
	}
  }
}

if ( !isset($options['user'])) {	// Check if user defined. Login and Pass is checked later with MD5 checksum
  printLogAndDie("User is not defined");
}
else if (!isset($options['pass'])) { // Check if password defined. Login and Pass is checked later with MD5 checksum
  printLogAndDie("Password is not defined");
}
else if (!isset($options['status']) || !preg_match('/^\d+\.txt$/',$options['status'])) {	// Check if status file defined
  printLogAndDie("Status file is not defined or incorrect");
}


removeOldStat();					// Remove old stat files here
									// Check login/password
if (calcCheckSum($options['user'],$options['pass']) != $config['main']['MD5check']) {	// Password incorrect
  sleep($INVALID_PASS_SLEEP);
  updateStatusFile($options['status'],"Configuration error. Can't restart server");
  printLogAndDie("Invalid login/password");
}
										// Define commands for FM server
$cmd = $config['main']['fmsadmin_path'].' -u '.$options['user'].' -p '.$options['pass'].' -y ';
$restartCmd = $cmd.' restart server';	// Restart server command
$startCmd = $cmd.' start server';		// Start server command
$checkCmd = $cmd.' LIST FILES';			// Command to check if server alive
$discCmd = $cmd.' DISCONNECT CLIENT';	// Command to disconnect clients


									// Connect to server if required
$log->message("Try to restart server. Status file: ".$options['status']);
									// Connect to server
if($config['main']['fms_server'] != 'LOCAL') {
  updateStatusFile($options['status'],"Connecting to server");
  try	{													// Connect to remote server
    $ssh = new Net_SSH2($config['main']['fms_server']);
    if (array_key_exists('ssh_login',$config['main'])) {	// Use login /pass to connect to distant server
      if (!$ssh->login($config['main']['ssh_login'], $config['main']['ssh_password'])) {
        printLogAndDie('Error connecting to server');
      }
	}
	else {													// Use RSA key authentication to connect to distant server
      $key = new Crypt_RSA();
      if(!$key->loadKey(file_get_contents($config['main']['fms_public_key_path']))) {
	    updateStatusFile($options['status'],"Configuration error. Can't restart server");
	    printLogAndDie("Error loading key");
      }
      if (!$ssh->login($config['main']['fms_user'],$key)) {
	    updateStatusFile($options['status'],"Can't connect to server");
	    printLogAndDie('Error connecting to server');
	  }
    }
  }
  catch (Exception $e) {									// Error connecting to server
    updateStatusFile($options['status'],"Can't connect to server");
    printLogAndDie("Error connecting to server".$e->getMessage());
  }
  $log->message("Connected to server");
}	

if ($options['action']=='disconnect') { // Disconnect clients
  updateStatusFile($options['status'],"Disconnecting clients");
  try	{								// Run disconnect clients command
    if ($config['main']['fms_server'] == 'LOCAL') {
      $res = shell_exec($discCmd);		// Run local command
    }
    else {
      $res = $ssh->exec($discCmd);		// Run command on distant server
//    $res = $ssh->exec('ls -l');
    }
    $log->message("Disconnecting clients result: ".$res);
    updateStatusFile($options['status'],"Server response: ".$res); 
  }
  catch (Exception $e) {				// Error disconnecting clients
    printLogAndDie("Send command error: ".$e->getMessage());
    updateStatusFile($options['status'],"Error restarting server");
  }
}
else if ($options['action']=='restart') { // Restart server
  updateStatusFile($options['status'],"Stopping server");
  try	{								// Run restart server command
    if ($config['main']['fms_server'] == 'LOCAL') {
      $res = shell_exec($restartCmd);	// Run local command
    }
    else {
      $res = $ssh->exec($restartCmd);	// Run command on distant server
//    $res = $ssh->exec('ls -l');
    }
    $log->message("Restart server result: ".$res);
  }
  catch (Exception $e) {				// Error restarting server
    printLogAndDie("Send command error: ".$e->getMessage());
    updateStatusFile($options['status'],"Error restarting server");
  }

										// Check server status
  $tryCnt = 1;							// Attempts' counter
  while (1) {
    try {								// Check if server alive
      if ($config['main']['fms_server'] == 'LOCAL') {
  	    $res = shell_exec($checkCmd);
      }
      else {
        $res = $ssh->exec($checkCmd);
      }
      $log->message("Check server result: ".$res);
      if ($res && !preg_match('/^Error:/m',$res)) { // No errors in result string
    	updateStatusFile($options['status'],"Server started");
        $log->message("Server started");
	    exit; 							// Server restarted, exit here
      }
    }
    catch (Exception $e) {				// Some  error
      updateStatusFile($options['status'],"Error checking server status");
      printLogAndDie("Send check command error: ".$e->getMessage());
    } 
    updateStatusFile($options['status'],'Trying to start server (attempt #'.$tryCnt.')');
    try {								// Try to start server
      if ($config['main']['fms_server'] == 'LOCAL') {
 	   $res = shell_exec($startCmd);
      }
      else {
        $res = $ssh->exec($startCmd);
      }
      $log->message("Start server (iteration $tryCnt) result: ".$res);
    }
    catch (Exception $e) {				// Some  error
      $log->message("Start server error: ".$e->getMessage());
    } 
    $tryCnt++; 
    if ($tryCnt > $MAX_START_TRIES) {	// Check max number of tries
      updateStatusFile($options['status'],"Can't start server");
	  $log->message("Can't start server. Give up.");
      exit;							// Give up here, can't start server
    }
    sleep($START_TRIE_TIMEOUT);		// Wait before next try to start the server
  }
} 
exit;								// Finish here

//
// checkConfig - check config parametrs
// Call:	$err = checkConfig($config);
// Where:	$config - config parameters
//			$err - error if any
//
function checkConfig ($config) {
  $errors = array();				// List of errors
  if (!isset($config['main']['fms_server'])) {
    array_push ($errors,"File Maker server is not defined in ini file.");
  }
  if (!isset($config['main']['fmsadmin_path'])) {
    array_push ($errors,"File Maker admin path is not defined in ini file.");
  }
  if (count($errors)) {				// Some errors in config - exit
    return "Errors: ". implode("<br>", $errors);
  }
  return '';
}									// -- checkConfig --

//
// printLogAndDie - log and print  message and exit
// Call:	printLogAndDie($msg)
// Where:	$msg - message to write in log file
//
function printLogAndDie($str) {
  global $log;
  $log->message($str);
  exit;
}								// -- printLogAndDie --

//
// error_handler - catch notice and warnings
//
function error_handler($errno, $errstr) {
    global $log;
    if($errno == E_WARNING) {
	  $log->message("Warning: ".$errstr);        
//  	throw new Exception($errstr);
    } else if($errno == E_NOTICE) {
//      throw new Exception($errstr);
	  $log->message("Notice: ".$errstr); 
	}
}								// -- error_handler --

//
// updateStatusFile - update status file
//
function updateStatusFile($statusFile,$body) {
  global $config;
  file_put_contents($config['main']['stat_dir'].'/'.$statusFile,$body,LOCK_EX);
}

//
// removeOldStat - remove old status file
//
function removeOldStat () {
  global $config, $log;
  global $STAT_LIFETIME;
  if (!file_exists($config['main']['stat_dir'])) {			// Check if dir exists
    return '';
  }
  $files = scandir($config['main']['stat_dir']); // Scan directory
  if(is_array($files)) {			// Files/Directories found
    foreach($files as $fileName) {
      if($fileName == '.' || $fileName == '..' ||  	// Skip home and previous listings
	     !preg_match('/^\d+\.txt$/',$fileName))		// Not status file
	  {
        continue;
	  }
      if ((time()-filemtime ($config['main']['stat_dir'].'/'.$fileName)) > $STAT_LIFETIME) {
        $log->message("Remove old stat file ".$config['main']['stat_dir'].'/'.$fileName);
		unlink($config['main']['stat_dir'].'/'.$fileName);
	  }
    }
  }
  return '';
}

?>