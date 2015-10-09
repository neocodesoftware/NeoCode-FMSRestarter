<?php

// subs.php - common subs
//
// Vers. ALPHA, YP 05/12/2015
//
// Common subs files
//
// Copyright © 2015 Neo Code Software Ltd
// Artisanal FileMaker & PHP hosting and development since 2002
// 1-888-748-0668 http://store.neocodesoftware.com
//
// History:
// ALPHA, YP 05/12/2015 - Alpha release
//


//
// calcCheckSum - return checksum for username and password
// Call:	$checksum = calcCheckSum($username,$pass)
// Where:	$username - username
//			$pass - password
//			$checksum - calculated checksum
//
function calcCheckSum($username,$pass) {
  return md5($username.' '.$pass);
}

?>