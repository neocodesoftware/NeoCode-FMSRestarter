<?php

// index.php - Process web requests on restarting FileMaker server, disconnecting clients or generating MD5 key
// Vers. 1.0 , YP 10/09/2015
//
// Restart remote FileMaker server.
// Receive login/pass from user, check md5 checksum with stored in ini file and send restart/disconnect users request. Show status page
//
// index.php?act=checksum - to calculate checksum
//
// Copyright © 2015 Neo Code Software Ltd
// Artisanal FileMaker & PHP hosting and development since 2002
// 1-888-748-0668 http://store.neocodesoftware.com
//
// History:
// ALPHA, YP 04/20/2015 - Alpha release
// 1.0 , YP 10/09/2015 - Initial release
//


include_once('subs.php');			// common subs
include_once('class.Log.php');		// Log class
include_once('class.CheckStart.php');
									// Global vars
$INIFILE = 'restart.ini';
$INVALID_PASS_SLEEP = 2;			// Sleep time in case of invalid password

$message = '';						// Notification message
$action = '';						// Action we need to do
									// Start here
set_error_handler("error_handler", E_ALL); // Catch all error/notice messages

if (!file_exists($INIFILE)) { // Check if config file exists
  showPage($action,"Config file not found","");
}
									// Load config
$config = parse_ini_file($INIFILE, true);
if ($error = checkConfig($config)) {// Check required parameters from config file
  showPage($action,$error,"");
}
$ckStart = new CheckStart($config['main']['log_dir'].'index.lock');
$log = new LOG($config['main']['log_dir'].'index.log');

$action = array_key_exists('act',$_REQUEST) ? $_REQUEST['act'] : '';			// Action

if ($action == 'checksum') {		// Calculate checksum and show it to user
  if (array_key_exists('UserName', $_REQUEST) && array_key_exists('Password', $_REQUEST)) {
    $message = "The checksum is: ".calcCheckSum($_REQUEST['UserName'],$_REQUEST['Password']);
    $action = '';
  }
  showPage($action,$message,"");
}

else if ($action == 'status') {		// Show status page
  if (!array_key_exists('id',$_REQUEST) || 				// Check if we have file id
      !preg_match('/^\d+\.txt$/',$_REQUEST['id']) ||	// Check if file Id correct
      !file_exists($config['main']['stat_dir'].$_REQUEST['id'])) // Check if status file exists
  {
	$message = "Status file not found";
    $action = '';
	showPage($action,$message,"");
  }
  showPage('status',file_get_contents($config['main']['stat_dir'].$_REQUEST['id']),$_REQUEST['id'],"");
}

else if (($action == 'disconnect' || $action == 'restart') &&  	// "Restart" or "Disconnect" button is clicked
          array_key_exists('UserName', $_REQUEST) && array_key_exists('Password', $_REQUEST)) 		// username and pass submitted
{
  if(!$ckStart->canStart()) {		// Check if script is already running. Doesn't allow customer to send multiple restart requests
    showPage($action,"Script is already running.","");
  }
  if ($_SERVER['REQUEST_METHOD'] != 'POST') {			// Allow only POST requests with login and password
	$log->message("Invalid request type: ".$_SERVER['REQUEST_METHOD']);
	showPage($action,"Invalid request type","");
  }
  if (calcCheckSum($_REQUEST['UserName'],$_REQUEST['Password']) != $config['main']['MD5check']) {	// Password incorrect
	sleep($INVALID_PASS_SLEEP);
	$log->message("Invalid login/password");
	showPage($action,"Invalid login/password","");
  }
 									// Fork process to restart the server
  $log->message("Send request to server");
  $statusFile = rand(0,1000000).'.txt';
  $cmd = str_replace('PARAMETERS','--action='.$action.' --user='.$_REQUEST['UserName'].' --pass='.$_REQUEST['Password'].' --status='.$statusFile,$config['main']['exec_cmd']);

   try {
    pclose(popen($cmd, 'r'));
//	  exec("$cmd > /dev/null 2>&1 &",$output,$res);
//      $log->message("Started process status: $res. output: ".var_export($output,1));
	
  }
  catch (Exception $e) {				// 
    $log->message("Send command error: ".$e->getMessage());
    printLogAndDie("Send command error: ".$e->getMessage());
    showPage($action,"Error connecting server","");
  }
  showPage('status','Your request is sent',$statusFile);
}

showPage($action,$message,"");								// Finish here

//
// checkConfig - check config parametrs
// Call:	$err = checkConfig($config);
// Where:	$config - config parametrs
//			$err - error if any
//
function checkConfig ($config) {
  $errors = array();				// List of errors
  if (!isset($config['main']['MD5check'])) {
    array_push ($errors,"Login and Password are not defined in ini file.");
  }
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
// showPage - show page to user
//
function showPage($action,$message,$statusFile) {
?>
  <html>
  <header>
  <style>
    html {
      background: #F1F1F1 none repeat scroll 0% 0%;
	  font-size: 5vmin;
	  font-family: "Open Sans",sans-serif;
    }
    .container{
      width: 100%;
    }

    .mydiv {
      margin: auto;
      width: 15em;
      height: 12em;
	  text-align:center;
	}
	.logo {
		margin: auto;
		margin-top: -2em;
		width: 8em;
        height: 8em;
		display: block;
	}	
	.input {
		background: #FBFBFB none repeat scroll 0% 0%;
		margin: 0.1em 0.3em 0.8em 0em;
        padding: 0.15em;
		font-size: 1em;
		border: 0.05em solid #DDD;
		box-shadow: 0em 0.05em 0.1em rgba(0, 0, 0, 0.07) inset;
		background-color: #FFF;
		color: #32373C;
		width: 100%;
    }
	
	.input:focus {
      border-color: #5B9DD9;
      box-shadow: 0em 0em 0.1em rgba(30, 140, 190, 0.8);
	}
	
	.button {
	  font-size: 0.65em;	  
	  height: 1.5rem;
	  line-height: 1.4rem;
	  padding: 0rem 0.6rem 0.6rem;
	  background: #00A0D2 none repeat scroll 0% 0%;
	  border-color: #0073AA;
	  box-shadow: 0em 0.05rem 0rem rgba(120, 200, 230, 0.5) inset, 0px 1px 0px rgba(0, 0, 0, 0.15);
	  color: #FFF;
	  cursor: pointer;
	  border-width: 0.05rem;
	  border-style: solid;
	  border-radius: 0.15rem;
	  white-space: nowrap;
	  box-sizing: border-box;
	  font-family: inherit;
	  
	  vertical-align: baseline;
	  text-decoration: none;
	  display: inline-block;
	}   
	.button:hover {
		border-color: #0E3950;
	    box-shadow: 0em 0.05em 0em rgba(120, 200, 230, 0.6) inset, 0px 0px 0px 1px #5B9DD9, 0px 0px 2px 1px rgba(30, 140, 190, 0.8);
	    background: #0091CD none repeat scroll 0% ;
	}

	.label {
		color: #777;
       font-size: 0.7em;
	   cursor: pointer;
	}
	.form {
	  background: #FFF none repeat scroll 0% 0%;
	  text-align:left;
	  padding: 1.3em 1.2em 0.2em;
	  overflow: hidden;
	  box-shadow: 0em 0.05em 0.15em rgba(0, 0, 0, 0.13);
	}
	.msg {
		margin-bottom:1em;
		font-size: 0.8em;
		display:block;
		padding: 1em;
	    border-left: 0.2em solid #DD3D36;
	    background: #FFF none repeat scroll 0% 0%;
	    box-shadow: 0em 0.05em 0.05em 0em rgba(0, 0, 0, 0.1);	
	    text-align:left;		
	}
  </style>
  <script>

  </script>
  </header>
  <body>
   <div class="container">
   <div class="mydiv">
   <img class="logo" src="img/neocode250x250logo.png">
   <? if ($message) { ?> 
	  <div class="msg"> <?= $message ?></div> 
    <? } ?>
   <div class="form">
<? if ($action == 'status') { ?>
     <center>
	 <input class="button" type="button" value="Check Status"  onclick="location.href = 'index.php?act=status&id=<?= $statusFile ?>';">
	 &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
     <input class="button" type="button" value="Return"  onclick="location.href = 'index.php';">
	 </center>
<? } else { ?>	 
   	<form action="index.php"  method="post" name="myForm">
    <input type="hidden" name="act" value="<?= $action ?>">
	<label class="label" for="UserName">Username: </label><br>
	  <input class="input" type="text" name="UserName" id="UserName" value=""><BR>
	<label class="label" for="Password">Password: </label><br>
	<input class="input"  type="password" name="Password" id="Password"><BR>
     <? if ($action == 'checksum') { ?>
	   <input class="button" type="button" value="Submit" onClick="document.forms['myForm'].submit();">
	 <? } else { ?>
	   <center>
	   <input class="button" type="button" value="Restart" onClick="document.forms['myForm'].act.value='restart';document.forms['myForm'].submit();">
	   &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	   <input class="button" type="button" value="Disconnect" onClick="document.forms['myForm'].act.value='disconnect';document.forms['myForm'].submit();">
       </center>
	 <? } ?>
	 </form>
<? } ?>	 
</div>
  </div>	 
  </div>
  </body>
  </html>
 <?php 
   exit;
}								// -- showPage --

?>