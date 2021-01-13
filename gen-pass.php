<?php

(PHP_SAPI !== 'cli' || isset($_SERVER['HTTP_USER_AGENT'])) && die('ERROR: This is a command-line program.');

if (isset($argc) && $argc > 1) {
  $password=$argv[1];

  echo password_hash($password,PASSWORD_DEFAULT);
  echo "\n";
  
} else {

   echo "ERROR: You must send as a command line argument of the password to convert.\n";

}

?>
