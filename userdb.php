<?php
/*
**	This function returns TRUE only if a connection is made
**	successfully -and- the particular problem with the cookie
**	is that it has timed out. If the return value is FALSE,
**	the caller should examine the $complaint variable.
**
**	See checkNextStep() for other actions taken on return
**	values from keycodeauth.
*/

#require_once("log_global.php");

## I. SETUP

function connect_auth_sock(&$sock, &$complaint)
{
    if (!$sock) {
        $configInfo = read_config("server.json");
 
	$sock = fsockopen($configInfo['serverIP'], $configInfo['serverAuthPort'], $errno, $errstr, 30);
	if (!$sock) {
	    $complaint = "Auth Failure: There was a technical problem.  ".
		         "Please try again later.";
	    return FALSE;
	}
    }
    return TRUE;
}

function connect_ctl_sock(&$sock, &$complaint)
{
    if (!$sock) {
        $configInfo = read_config("server.json");
 
	$sock = fsockopen($configInfo['serverIP'], $configInfo['serverCtlPort'], $errno, $errstr, 30);    
	if (!$sock) {
	    $complaint = "Control Failure: There was a technical problem.  ".
		         "Please try again later.";
	    return FALSE;
	}
    }
    return TRUE;
}


function load_db() {

 $configInfo = read_config("database.json");
 
 try {
   $dbh = new PDO("mysql:host=localhost;dbname=" . $configInfo['dbName'], $configInfo['dbUser'],$configInfo['dbPass']);
  } catch (PDOException $e) {
    echo 'Connection failed: ' . $e->getMessage();
  }    
  return $dbh;
  
}

function read_config($configName) {

  $configInfo = json_decode(file_get_contents("config/" . $configName), true);

  return $configInfo;
  
}

function set_cookie_url() {

  $config = read_config("general.json");
  $siteURL = $config['gameURL'];
  
  $urlParts = explode(".",$siteURL);
  $lastBit = sizeof($urlParts) - 1;
  $cookieURL = "." . $urlParts[$lastBit - 1] . "." . $urlParts[$lastBit];

  return $cookieURL;

}

## IA. General functions

function socket_error($readSocket,$seq,$error) {


  error_log("Returning $seq ERR $error\n",3,"/var/log/userdb.log");
  socket_write($readSocket,"$seq ERR $error\n");
  
}

function socket_ok($readSocket,$seq,$message) {

  error_log("Returning $seq OK $message\n",3,"/var/log/userdb.log");
  socket_write($readSocket,"$seq OK $message\n");
  
}

function socket_nerror($readSocket,$seq,$message) {

  socket_error($readSocket,$seq,urlencode($message));

}

function socket_nok($readSocket,$seq,$message) {

  socket_ok($readSocket,$seq,urlencode($message));

}

## II. Auth Server

## IIA. Auth Server: Login

function checkNextStep(&$auth_sock, $uname, $code, $return, &$complaint) {

   if (!connect_auth_sock($auth_sock, $complaint)) {
      return FALSE;
   }

   fputs($auth_sock, ":" .
	 urlencode(strtolower($uname))	. " " .
	 $code				. " " .
	 "keycodeauth"			. " " .
	 "1"				. "\n");
   $result = chop(fgets($auth_sock, 2048));

   if (substr($result, 2, 2) == "OK") {
      $type = getNewProperty($auth_sock, $uname, $code, $complaint, "account_type");
      if ($email && $email != "[unknown]") {
         $email_stamp = getNewProperty($auth_sock, $uname, $code, $complaint, "email-ping:stamp");
	 if ($email_stamp && time() > $email_stamp + 86400) {
	    Header("Location: /user/ping.php");
	    return TRUE;
	 }
      }
      if ($return && substr($return,0,5) != "login") {
          Header("Location: $return");
      } else {
          Header("Location: overview.php");
      }
      return TRUE;
   }
   $problem = urldecode(substr($result, 6));
   switch ($problem) {

   case "TOS":
      Header("Location: agree-tos.php");
      return TRUE;
   case "USER HAS NO EMAIL":
      Header("Location: change-email.php");
      return TRUE;   
   case "UNPAID":
      Header("Location: overview.php?expired=true");
      return TRUE;
        global $supportEmail;
	if (!$supportEmail) {
          $config = read_config("general.json");
          $suportEmail = $config['supportEmail'];
       }
       $complaint = "Your account has been blocked; please contact <A href=\"mailto:$supportEmail\">customer support</A> if you have any questions about this or feel this was done in error.";
       return FALSE;
   }
   $complaint = "UserDB error: Nextstep Problem" . $problem;
   return FALSE;
}


function checkTimeOut(&$auth_sock, $uname, $code, &$complaint) {
   if (!connect_auth_sock($auth_sock, $complaint)) {
      return TRUE;
   }
   fputs($auth_sock, ":" .
	 urlencode(strtolower($uname))	. " " .
	 $code				. " " .
	 "keycodeauth"			. " " .
	 "1"				. "\n");

   $result = chop(fgets($auth_sock, 2048));
   if (substr($result, 2, 2) == "OK") {
      return FALSE;
   }
   $problem = urldecode(substr($result, 6));
   if ($problem == "BAD KEYCODE" || $problem == "ACCOUNT BLOCKED") {
     return TRUE;      
   } else if ($problem == "TOS") {
     $thisPage = basename($_SERVER['PHP_SELF'],'.php');
     if ($thisPage != "agree-tos") {
       Header("Location: agree-tos.php");
       exit;
     } else {
       return FALSE;
     }
   } else if ($problem == "USER HAS NO EMAIL") {
     $thisPage = basename($_SERVER['PHP_SELF'],'.php');
     if ($thisPage != "change-email" && $thisPage !="verify-email") {
       Header("Location: change-email.php");
       exit;
     } else {
       return FALSE;
     }     
   }
   $complaint = "UserDB error: " . $problem;
   return TRUE;
}

function passLogin(&$auth_sock, $uname, $pwd, &$user, &$pass, &$complaint) {

   if (!connect_auth_sock($auth_sock, $complaint)) {
      return FALSE;
   }
   fputs($auth_sock, ":" .
	 urlencode(strtolower($uname))	. " " .
	 "XXX"				. " " .
	 "passlogin"			. " " .
	 "1"				. " " .
	 $pwd		. "\n");
   $result = chop(fgets($auth_sock, 2048));
   if (substr($result, 2, 2) == "OK") {
      $code = substr($result, 5);

      $cookieURL = set_cookie_url();
      
      setcookie("user", $uname, 0, "/", $cookieURL);
      setcookie("pass", $code,  0, "/", $cookieURL);

      $user = $uname;
      $pass = $code;
      return TRUE;
   }
   $problem = urldecode(substr($result, 6));

   switch($problem) {
   case "BAD PASSWORD":
   case "NO SUCH USER":
   case "BAD KEYCODE":
      $complaint = "Authentication Error: Either that user name does not exist or the password is incorrect.";
      break;
   case "ACCOUNT BLOCKED":
      $configInfo = read_config("general.json");

      $complaint = "Your account has been blocked; please <A href=\"mailto:" . $configInfo['supportEmail'] . "\">contact us</A> if you have any questions about this or feel this was done in error.";
      break;
   default:
      $complaint = "UserDB error: Auth Problem " . $problem;
      break;
   }
   return FALSE;
}

function validateUsername($ctl_sock, $username, &$complaint) {
    if (!connect_ctl_sock($ctl_sock, $complaint)) {
        return FALSE;
    }
    fputs($ctl_sock, "validate 1 " . urlencode($username) . "\n");
    $result = chop(fgets($ctl_sock, 2048));
    if (substr($result, 2, 2) == "OK") {
	return TRUE;
    }
    $problem = substr($result, 6);
    switch ($problem) {
      case "EXISTS":
        $complaint = "Someone has already chosen that account name, please choose another. " .
                     "Be imaginative, using middle initials, or names that " .
                     "might otherwise be uncommon.";
        return FALSE;
    case "PURGED":
	$complaint = "That account name is not available, please choose another.  " .
                     "Be imaginative, using middle initials, or names that " .
                     "might otherwise be uncommon.";
        return FALSE;
    case "TOO SHORT":
        $complaint = "That account name is too short.";
        return FALSE;
    case "TOO LONG":
	$complaint = "That account name is too long.";
        return FALSE;
    case "INITIAL":
        $complaint = "Your account name should start with a letter (a..z).";
        return FALSE;
    default:
        if (substr($problem, 0, 8) == "INVALID:") {
            $complaint = "Bad character '" . substr($username, substr($problem, 8) - 1, 1) . "' in account name.";
        } else {
            $complaint = "Unexpected error: $problem.";
	}
        return FALSE;
    }
}


## IIB Auth Server Email

function changeEmail(&$auth_sock, $uname, $code, $email, &$complaint) {
    if (!connect_auth_sock($auth_sock, $complaint)) {
        return FALSE;
    }
    fputs($auth_sock, ":" .
	  urlencode(strtolower($uname))   . " " .
	  XXX                            . " " .
	  "setemail"                     . " " .
	  "1"                            . " " .
	  urlencode($email)              . "\n");
    $result = chop(fgets($auth_sock, 2048));
    if (substr($result, 2, 2) == "OK") {
	return TRUE;
    }
    $complaint = "UserDB error: " . $problem;
    return FALSE;
}

function checkEmail(&$auth_sock, $email, &$uname, &$complaint) {

   if (!connect_auth_sock($auth_sock, $complaint)) {
      return FALSE;
   }

   fputs($auth_sock, ":" .
	 urlencode($email)	. " " .
	 "XXX"				. " " .
	  "emaillookup"			. " " .
	 "1"				. "\n");
   $result = chop(fgets($auth_sock, 2048));

   if (substr($result, 2, 2) == "OK") {
      $uname = substr($result, 5);

      return TRUE;

   }

   $complaint = urldecode(substr($result, 6));
   return FALSE;
   
}


function getPing(&$auth_sock, $uname, $code, &$pinginfo, &$complaint) {
   if (!connect_auth_sock($auth_sock, $complaint)) {
      return FALSE;
   }
   fputs($auth_sock, ":" .
	 urlencode(strtolower($uname))	. " " .
	 $code				. " " .
	 "getping"			. " " .
	 "1"				. "\n");

   $result = chop(fgets($auth_sock, 2048));

   if (substr($result, 2, 2) == "OK") {

      $pingResults = explode(" ", urldecode($result));
      
      $pinginfo['ID'] = $pingResults[2];
      $pinginfo['email'] = $pingResults[3];
      $pinginfo['code'] = $pingResults[4];
      return TRUE;
   }

   $problem = urldecode(substr($result, 6));
   
   if ($problem == "BAD KEYCODE" || $problem == "ACCOUNT BLOCKED") {
      Header("Location: login.php");
      return FALSE;
   } else if ($problem == "NO PING") {
     $complaint = "";
     return FALSE;
   }
   $complaint = "UserDB error: " . $problem;
   return FALSE;
   
}

function pingUser(&$auth_sock, $uname, $email, $code, &$complaint) {
   if (!connect_auth_sock($auth_sock, $complaint)) {
      return FALSE;
   }
   fputs($auth_sock, ":" .
	 urlencode(strtolower($uname))	. " " .
	 $code				. " " .
	 "pinguser"			. " " .
	 "1"				. " " .
	 urlencode($email)		. "\n");

   $result = chop(fgets($auth_sock, 2048));
   if (substr($result, 2, 2) == "OK") {
      return TRUE;
   }
   $problem = urldecode(substr($result, 6));
   if ($problem == "BAD KEYCODE" || $problem == "ACCOUNT BLOCKED") {
      Header("Location: login.php");
      return FALSE;
   }
   $complaint = "UserDB error: " . $problem;
   return FALSE;
}

## IIB. Auth Server: Keycode


function requestTempKeycode(&$auth_sock, $uname, &$user, &$pass, &$guarantee, &$complaint) {

   if (!connect_auth_sock($auth_sock, $complaint)) {
      return FALSE;
   }
   fputs($auth_sock, ":" .
	 urlencode(strtolower($uname))	. " " .
	 "XXX"				. " " .
	 "tempkeycode"			. " " .
	 "1"				. "\n");

   $result = chop(fgets($auth_sock, 2048));
   if (substr($result, 2, 2) == "OK") {
      $code = substr($result, 5);

      $user = $uname;
      $pass = $code;

      fputs($auth_sock, ":" .
	 urlencode(strtolower($uname))	. " " .
	 "XXX"				. " " .
	 "tempguarantee"			. " " .
	 "1"				. "\n");

      $result = chop(fgets($auth_sock, 2048));

      if (substr($result, 2, 2) == "OK") {

        $guarantee = substr($result, 5);
        return TRUE;
	
      } else {

        $complaint = urldecode(substr($result, 6));
	return FALSE;
      }
   }
   $problem = urldecode(substr($result, 6));

   switch($problem) {
   case "NO SUCH USER":
      $complaint = "Authentication Error: Either that user name does not exist or the password is incorrect.";
      break;
   case "ACCOUNT BLOCKED":
      $configInfo = read_config("general.json");
      $supportEmail = $configInfo['supportEmail'];
      
      $complaint = "Your account has been blocked; please <A href=\"mailto:$supportEmail\">contact us</A> if you have any questions about this or feel this was done in error.";
      break;
   default:
      $complaint = "UserDB error: Auth Problem " . $problem;
      break;
   }
   return FALSE;
}

function is_hash_ok($uid,$hash,&$error) {

  $keycodeinfo = get_keycode_for_user($uid);
  $keycode = $keycodeinfo['keycode'];
  $userinfo = lookup_user($uid);
  $real_hash = md5($userinfo['name'] . $keycode . "NONE");

  if ($hash == $real_hash) {
    return TRUE;
  } else {
    $error = "BAD HASH";
    return FALSE;
  }
}

  
function is_keycode_ok($uid,$keycode,&$error) {

  # Keycodes are good for 48 hours, then log back in
  $keycodeLife = 3600 * 48;
  
  $saveCode = get_keycode_for_user($uid);

  if (!$keycode ||
      $keycode != $saveCode['keycode'] ||
      time() - $saveCode['keycode_stamp'] > $keycodeLife) {

      $error = "BAD KEYCODE";
      return FALSE;

  } else {

      return TRUE;

  }

}

function is_password_ok($uid,$password,&$error) {

  $hashed = get_password_for_user($uid);

  if (!password_verify($password,$hashed)) {

    $error = "BAD PASSWORD";
    return 0;

  }

  return 1;

}

function gen_keycode() {

  return ((time() >> 1) + rand(0,1<<29));

}

function gen_keycode_guarantee($uid) {

  $userinfo = lookup_user($uid);
  return hash('ripemd160',$userinfo['ID'] . $userinfo['next_stamp'] . $userinfo['creation_time']);

}

function get_keycode_for_user($uid) {

  global $dbh;

  $SQL = "SELECT * FROM keycodes WHERE ID=:uid";
  
  $statement = $dbh->prepare($SQL);
  $statement->bindParam(":uid",$uid,PDO::PARAM_INT);
  $statement->execute();

  return $statement->fetch(PDO::FETCH_ASSOC);

}

function get_password_for_user($uid) {

# This should be commented out or something after initial work

  $userinfo = lookup_user($uid);
  if (!$userinfo['user_updated']) {
    require_once("userdb-convert.php");
    convert_old_user($uid);
  }

  global $dbh;
  
  $SQL = "SELECT password FROM users ";
  $SQL .= "WHERE ID=:uid";

  $statement = $dbh->prepare($SQL);
  $statement->bindParam(":uid",$uid,PDO::PARAM_INT);
  $statement->execute();

  return $statement->fetchColumn();
  
}

function is_guarantee_ok($uid,$guarantee,&$error) {

  $verified_guarantee = gen_keycode_guarantee($uid);

  if ($verified_guarantee == $guarantee) {
    return TRUE;
  } else {
    $error = "BAD TOKEN";
    return FALSE;
  }

}
   
function set_keycode_for_user($uid,$temporary = 0) {

  global $dbh;
  
  $SQL = "DELETE FROM keycodes WHERE ID=:uid";

  $statement = $dbh->prepare($SQL);
  $statement->bindParam(":uid",$uid,PDO::PARAM_INT);
  $statement->execute();

  $keycode = gen_keycode();
  if ($temporary) {
    $kc_stamp = time() - (3600 * 47);
  } else {
    $kc_stamp = time();
  }

  $SQL = "INSERT INTO keycodes (ID,keycode,keycode_stamp) ";
  $SQL .= "VALUES (:id, :keycode, :stamp)";
  
  $statement = $dbh->prepare($SQL);
  $statement->bindParam(":id",$uid,PDO::PARAM_INT);
  $statement->bindParam(":keycode",$keycode,PDO::PARAM_INT);
  $statement->bindParam(":stamp",$kc_stamp,PDO::PARAM_INT);  
  $statement->execute();

  return $keycode;
}

function set_password_for_user($uid,$password) {

  global $dbh;
  $newPass = password_hash($password,PASSWORD_DEFAULT);

  $SQL = "UPDATE users ";
  $SQL .= "SET password=:newPass ";
  $SQL .= "WHERE ID=:uid ";

  $statementUpdate = $dbh->prepare($SQL);
  $statementUpdate->bindParam(":newPass",$newPass,PDO::PARAM_STR);
  $statementUpdate->bindParam(":uid",$uid,PDO::PARAM_INT);       
  $statementUpdate->execute();


}

## IIC. Auth Server: Properties

function convertAccount(&$auth_sock, $uname, $code, $towhat, &$complaint) {
   if (!connect_auth_sock($auth_sock, $complaint)) {
      return FALSE;
   }
   fputs($auth_sock, ":" .
	 urlencode(strtolower($uname))	. " " .
	 $code				. " " .
	 "convertaccount"		. " " .
	 urlencode($towhat)		. " " .	 
	 "1"				. "\n");

   $result = chop(fgets($auth_sock, 2048));
   if (substr($result, 2, 2) == "OK") {
      return TRUE;
   }
   $problem = urldecode(substr($result, 6));

   switch($problem) {
   
     case "BAD PASSWORD":
     case "NO SUCH USER":
     case "BAD KEYCODE":
   
       $complaint = "Authentication Error: Either that user name does not exist or the password is incorrect.";
       break;

     case "ACCOUNT BLOCKED":
       $configInfo = read_config("general.json");

       $complaint = "Your account has been blocked; please <A href=\"mailto:" . $configInfo['supportEmail'] . "\">contact us</A> if you have any questions about this or feel this was done in error.";
      break;

    default:
      $complaint = "UserDB error: Auth Problem " . $problem;
      break;
     }

  return FALSE;
  
}
	      
function getNewProperty(&$auth_sock, $uname, $pass, &$complaint, $prop) {
   if (!connect_auth_sock($auth_sock, $complaint)) {
      return FALSE;
   }
   fputs($auth_sock, ":" .
   	 urlencode(strtolower($uname))	. " " .
   	 $pass				. " " .
   	 "getprop 1"			. " " .
   	 urlencode(strtolower($uname))	. " " .
   	 $prop				. "\n");

   $result = fgets($auth_sock, 16384);
   if (substr($result, 2, 2) == "OK") {
      return chop(substr($result, 5));
   }
   $complaint = "UserDB error: " . urldecode(chop(substr($result, 6)));
   return FALSE;
}

## III. Control Server

function banUser(&$ctl_sock, $uname, $reason, $whom, &$complaint) {
    if (!connect_ctl_sock($ctl_sock, $complaint)) {
        return FALSE;
    }
    fputs($ctl_sock,
          "ce_ban 1 "        .
          urlencode($uname)  . " " .
          urlencode($reason)   . " " .
          urlencode($whom) . "\n");	  
    $result = fgets($ctl_sock, 2048);
    if (substr($result, 2, 2) == "OK") {
        return TRUE;
    }
    $complaint = "UserDB error: " . substr($result, 6);
    return FALSE;
}

function unbanUser(&$ctl_sock, $uname, &$complaint) {
    if (!connect_ctl_sock($ctl_sock, $complaint)) {
        return FALSE;
    }
    fputs($ctl_sock,
          "ce_ban_clear 1 " .
          urlencode($uname)  . "\n");
    $result = fgets($ctl_sock, 2048);
    if (substr($result, 2, 2) == "OK") {
        return TRUE;
    }
    $complaint = "UserDB error: " . substr($result, 6);
    return FALSE;
}


function createUser(&$ctl_sock, $uname, $pwd, &$user, &$pass,
		    $email, &$complaint) {
   if (!connect_ctl_sock($ctl_sock, $complaint)) {
      return FALSE;
   }
   fputs($ctl_sock, "create 1 " .
	 urlencode(strtolower($uname))	. " " .
	 urlencode($pwd)			. " " .
	 urlencode(strtolower($email))	. "\n");

   $result = chop(fgets($ctl_sock, 2048));
   if (substr($result, 2, 2) == "OK") {
      $code = substr($result, 5);

      $cookieURL = set_cookie_url();
      setcookie("user", $uname, 0, "/", $cookieURL);
      setcookie("pass", $code,  0, "/", $cookieURL);

      $user = $uname;
      $pass = $code;
      return TRUE;
   }
   $problem = urldecode(substr($result, 6));
   switch($problem) {
   case "user exists":
      $complaint = "This user name is already in use. Try another.";
      break;
   default:
      $complaint = "UserDB error: " . $problem;
   }
   return FALSE;
}

function getProperty(&$ctl_sock, $uname, &$complaint, $prop) {
   if (!connect_ctl_sock($ctl_sock, $complaint)) {
      return FALSE;
   }
   fputs($ctl_sock, "getprop 1 " .
         urlencode(strtolower($uname))  . " " .
         urlencode($prop)               . "\n");
   $result = fgets($ctl_sock, 16384);
   if (substr($result, 2, 2) == "OK") {
      return chop(substr($result, 5));
   }
   $complaint = "UserDB error: " . urldecode(substr($result, 6));
   return FALSE;
}

function setPassword(&$ctl_sock, $uname, $old, $new, &$complaint,$guaranteed=0) {
   if (!connect_ctl_sock($ctl_sock, $complaint)) {
      return FALSE;
   }

   if ($guaranteed) {

     fputs($ctl_sock, "setpwdg 1 " .
	 urlencode(strtolower($uname))	. " " .
	 urlencode($old)		. " " .
	 urlencode($new)		. "\n");
	 
   } else {
   
     fputs($ctl_sock, "setpwd 1 " .
	 urlencode(strtolower($uname))	. " " .
	 urlencode($old)		. " " .
	 urlencode($new)		. "\n");

   }
   
   $result = chop(fgets($ctl_sock, 2048));
   if (substr($result, 2, 2) == "OK") {
      return TRUE;
   }
   $problem = urldecode(substr($result, 6));
   switch($problem) {
   case "no such user":
   case "wrong old password":
      $complaint = "Authentication Error: Either that user name does not exist or the password is incorrect.";
      break;
   default:
      $complaint = "UserDB error: " . $problem;
   }
   return FALSE;
}

function setProperty(&$ctl_sock, $uname, &$complaint, $prop, $val) {
   if (!connect_ctl_sock($ctl_sock, $complaint)) {
      return FALSE;
   }
   fputs($ctl_sock, "setprop 1 " .
	 urlencode(strtolower($uname))	. " " .
	 urlencode($prop)		. " " .
	 urlencode($val)		. "\n");
   $result = fgets($ctl_sock, 2048);
   if (substr($result, 2, 2) == "OK") {
      return TRUE;
   }
   $complaint = "UserDB error: " . urldecode(substr($result, 6));
   return FALSE;
}


## III.B. Status

function getAccountStatus(&$ctl_sock, $uname, $setting, &$complaint) {

      if (!connect_ctl_sock($ctl_sock, $complaint)) {
	  return FALSE;
      }
      fputs($ctl_sock, "account 1 " . urlencode($uname) . " get status\n");
      $result = fgets($ctl_sock, 2048);
      if (substr($result, 2, 2) == "OK") {
	  $result = chop(substr($result, 5));
	  if (strlen($result) > 0) {
	      $list = explode(" ", $result);
	      for ($i = 0; $i < count($list); $i++) {
		  if ($list[$i] == $setting) {
		      return TRUE;
		  }
	      }
	      return FALSE;
	  }
	  return FALSE;
      }
      $problem = urldecode(substr($result, 6));
      $complaint = "UserDB error: " . $problem;
      return FALSE;
  }

function setAccountStatus(&$ctl_sock, $uname, $setting, &$complaint) {
      if (!connect_ctl_sock($ctl_sock, $complaint)) {
	  return FALSE;
      }
      fputs($ctl_sock, "account 1 " . urlencode($uname) . " set status " . urlencode($setting) . "\n");
      $result = fgets($ctl_sock, 2048);
      if (substr($result, 2, 2) == "OK") {
	  return TRUE;
      }
      $problem = urldecode(substr($result, 6));      
      $complaint = "UserDB error: " . $problem;
      return FALSE;
}

function clearAccountStatus(&$ctl_sock, $uname, $setting, &$complaint) {
      if (!connect_ctl_sock($ctl_sock, $complaint)) {
	  return FALSE;
      }
      fputs($ctl_sock, "account 1 " . urlencode($uname) . " clear status " . urlencode($setting) . "\n");
      $result = fgets($ctl_sock, 2048);
      if (substr($result, 2, 2) == "OK") {
	  return TRUE;
      }
      $problem = urldecode(substr($result, 6));      
      $complaint = "UserDB error: " . $problem;
      return FALSE;
}

### III.C Pings

function checkPing(&$ctl_sock, $uname, &$complaint) {

   if (!connect_ctl_sock($ctl_sock, $complaint)) {
      return FALSE;
   }
   fputs($ctl_sock, "checkping 1 " .
	 urlencode(strtolower($uname))	. "\n");

   $result = chop(fgets($ctl_sock, 2048));

   if (substr($result, 2, 2) == "OK") {
      return TRUE;
   }

# USER HAS NO EMAIL, USER HAS NEW EMAIL, OR VALIDATED

   $complaint = urldecode(substr($result, 6));

   return FALSE;
   
}

function deletePing(&$ctl_sock, $uname, $update, &$complaint) {

   if (!connect_ctl_sock($ctl_sock, $complaint)) {
      return FALSE;
   }
   fputs($ctl_sock, "deleteping 1 " .
	 urlencode(strtolower($uname))	. " " .
	 urlencode($update)		. "\n");
   $result = fgets($ctl_sock, 2048);
   if (substr($result, 2, 2) == "OK") {
      return TRUE;
   }
   $complaint = "UserDB error: " . urldecode(substr($result, 6));
   return FALSE;
}


## III.D Storypoints

function storypoints_add(&$ctl_sock, $user, $points, $reason, $from, $comment, &$complaint)
{
    if (!connect_ctl_sock($ctl_sock, $complaint)) {
        return FALSE;
    }
    fputs($ctl_sock,
          "storypoints 1 "    .
          urlencode($user)    . " " .
          "add"               . " " .
	  urlencode($points)  . " " .
	  urlencode($reason)  . " " .
	  urlencode($from)    . " " .
	  urlencode("1")      . " " .
	  urlencode($comment) . "\n");
    $result = chop(fgets($ctl_sock, 2048));
    if (substr($result, 2, 2) == "OK") {
        return TRUE;
    }
    $complaint = "UserDB error: " . substr($result, 6);
    return FALSE;
}

function storypoints_log(&$ctl_sock, $user, &$value, &$complaint)
{
    if (!connect_ctl_sock($ctl_sock, $complaint)) {
        return FALSE;
    }
    fputs($ctl_sock,
          "storypoints 1 " .
          urlencode($user) . " " .
          "query log:full" . " " .
          "AGE"  . "\n");
    $result = chop(fgets($ctl_sock, 65536));
    if (substr($result, 2, 2) == "OK") {
        $value = substr($result, 5);
        if (strlen($value) > 0) {
            $value = explode(" ", $value);
	    reset($value);
	    $list = array();
	    while (list($index, $entry) = each($value)) {
	      $list[$index] = urldecode($entry);
	    }
	    $value = $list;
        } else {
            $value = array();
        }
        return TRUE;
    }
    $complaint = "UserDB error: " . substr($result, 6);
    return FALSE;
}

function storypoints_purchased(&$ctl_sock, $user, $length, &$complaint)
{
    if (!connect_ctl_sock($ctl_sock, $complaint)) {
        return FALSE;
    }
    fputs($ctl_sock,
          "storypoints 1 "    .
          urlencode($user)    . " " .
          "query purchased"   . " " .
	  urlencode($length)  . "\n");
    $result = chop(fgets($ctl_sock, 2048));
    if (substr($result, 2, 2) == "OK") {
        $result = chop(substr($result, 5));
	if (!$result) $result = 0;
	
        return $result;
    }
    $complaint = "UserDB error: " . substr($result, 6);
    return FALSE;
}

## III.E Payments

function billingOverview(&$ctl_sock, $uname, $months, $type, $cost, $pending, &$complaint) {

    if (!connect_ctl_sock($ctl_sock, $complaint)) {
        $complaint = "PAYMENT UNLOGGED ($uname/$type/$months months) " . $complaint;
        return FALSE;
    }

    if (!billingCredit($ctl_sock, $uname, $months, "unlogged", $complaint)) {
        $complaint = "PAYMENT UNLOGGED ($uname/$type/$months months) " . $complaint;         return FALSE;
    }	

    $oldType = getProperty($ctl_sock, $uname, $complaint, "account_type");
    if ($oldType == "trial") {
      setProperty($ctl_sock,$uname,$complaint,"account_type","regular");
    }
    if ($type == "premium") {
      setProperty($ctl_sock,$uname,$complaint,"premium",1);
    } else {
      setProperty($ctl_sock,$uname,$complaint,"premium",0);
    }

    logBilling($ctl_sock, $uname, $type, $months, $cost, $pending, $complaint);
       
}


function billingCredit(&$ctl_sock, $uname, $months, $log_who, &$complaint) {
    if (!connect_ctl_sock($ctl_sock, $complaint)) {
        return FALSE;
    }
    fputs($ctl_sock,
	  "billcredit 1 " .
	  urlencode($uname)          . " " .
	  urlencode($months)         . " " .
	  urlencode($log_who)        . "\n");
    $result = fgets($ctl_sock, 2048);
    if (substr($result, 2, 2) == "OK") {
	return chop(substr($result, 5));
    }
    $problem = substr($result, 6);
    $complaint = "UserDB error: " . $problem;
    return FALSE;
}

function logBilling(&$ctl_sock, $uname, $type, $amt, $cost, $pending, &$complaint) {

   if (!connect_ctl_sock($ctl_sock, $complaint)) {
      return FALSE;
   }
   fputs($ctl_sock, "logbill 1 " .
	 urlencode(strtolower($uname))	. " " .
	 urlencode($type)		. " " .
	 urlencode($amt)		. " " .
	 urlencode($cost)		. " " .
	 urlencode($pending)		. "\n");
	 
   $result = chop(fgets($ctl_sock, 2048));
   if (substr($result, 2, 2) == "OK") {
        return chop(substr($result, 5));
   }	

   $problem = urldecode(substr($result, 6));
   switch($problem) {
   case "USER UNKNOWN":
      $complaint = "That user does not exist";
      break;
   default:
      $complaint = "UserDB error: " . $problem;
   }
   return FALSE;
}

function logPaypal(&$ctl_sock, $uname, $item, $amount, $from, $txnid, &$complaint) {

   if (!connect_ctl_sock($ctl_sock, $complaint)) {
      return FALSE;
   }
   fputs($ctl_sock, "logpaypal 1 " .
	 urlencode(strtolower($uname))	. " " .
	 urlencode($item)		. " " .
	 urlencode($from)		. " " .
	 urlencode($txnid)		. " " .
	 urlencode($amount)		. "\n");


   $result = chop(fgets($ctl_sock, 2048));
   if (substr($result, 2, 2) == "OK") {
        return chop(substr($result, 5));
   }	

   $problem = urldecode(substr($result, 6));
   switch($problem) {
   case "USER UNKNOWN":
      $complaint = "That user does not exist";
      break;
   default:
      $complaint = "UserDB error: " . $problem;
   }
   return FALSE;
   
}

function unusedPaypalTxnid(&$ctl_sock,$txnid,&$complaint) {

    if (!connect_ctl_sock($ctl_sock, $complaint)) {
        $complaint = "Failed to connect to DB.";
        return FALSE;
    }
    fputs($ctl_sock,
	  "paypalcheck 1 " .
	  urlencode($txnid)          . "\n");
    $result = fgets($ctl_sock, 2048);
    if (substr($result, 2, 2) == "OK") {
	return TRUE;
    } else {
      $problem = substr($result, 6);
      $complaint = "UserDB error: " . $problem;
      return FALSE;
    }
}

## IV. Database

## IV.A. Users

function create_user($uname,$password,$email) {

  global $dbh;

  $SQL = "INSERT INTO users ";
  $SQL .= "(name, email, creation_time, password, pay_day, next_month, next_year, next_stamp, account_type, user_updated) ";
  $SQL .= "VALUES (:name, :email, :creation, :password, :day, :month, :year, :TS, 'trial', '3') ";

  $statement = $dbh->prepare($SQL);
  $statement->bindParam(":name",$uname,PDO::PARAM_STR);
  $statement->bindParam(":email",$email,PDO::PARAM_STR);  

  $when = time();
  $statement->bindParam(":creation",$when,PDO::PARAM_INT);

  $newPass = password_hash($password,PASSWORD_DEFAULT);
  $statement->bindParam(":password",$newPass,PDO::PARAM_STR);

  # Trial Period = 30 days
  $trialEnd = $when + (30 * 24 * 60 * 60);
  $trialEndDate = date('Y-m-d',$trialEnd);
  list($trialYear,$trialMonth,$trialDay) = explode("-",$trialEndDate);
  $trialMonth *= 1;
  $trialDay *= 1;
  $statement->bindParam(":day",$trialDay,PDO::PARAM_INT);
  $statement->bindParam(":month",$trialMonth,PDO::PARAM_INT);    
  $statement->bindParam(":year",$trialYear,PDO::PARAM_INT);
  $statement->bindParam(":TS",$trialEnd,PDO::PARAM_INT);

  $return = $statement->execute();

  return $return;
}

function describe_user($uid) {

  $user_type = lookup_account_type($uid);
  $user_status = query_property($uid,"account_status");
  $user_status = str_replace(","," ",$user_status);
			    
  $user_string = "($user_type;$user_status)";
  return $user_string;
  
}

function is_user_ok($uid,&$error) {

  $userinfo = lookup_user($uid);

  if (!$userinfo) {
    $error ="NO SUCH USER";
    return 0;
  } else if (is_deleted($userinfo['ID'])) {
    $error = "NO SUCH USER";
    return 0;  
  } else if (is_banned($userinfo['ID'])) {
    $error = "ACCOUNT BLOCKED";
    return 0;
  }

  return 1;

}

function lookup_email($uid) {

  global $dbh;
  
  $SQL = "SELECT email FROM users ";
  $SQL .= "WHERE ID=:uid ";

  $statement = $dbh->prepare($SQL);
  $statement->bindParam(":uid",$uid,PDO::PARAM_INT);
  $statement->execute();

  return $statement->fetchColumn();
  
}

function lookup_account_type($uid) {

  global $dbh;
  
  $SQL = "SELECT account_type FROM users ";
  $SQL .= "WHERE ID=:uid ";

  $statement = $dbh->prepare($SQL);
  $statement->bindParam(":uid",$uid,PDO::PARAM_INT);
  $statement->execute();

  return $statement->fetchColumn();
  
}

function lookup_next_stamp($uid) {

  global $dbh;
  
  $SQL = "SELECT next_stamp FROM users ";
  $SQL .= "WHERE ID=:uid ";

  $statement = $dbh->prepare($SQL);
  $statement->bindParam(":uid",$uid,PDO::PARAM_INT);
  $statement->execute();

  return $statement->fetchColumn();
  
}

function lookup_uid_by_name($uname) {

  global $dbh;
  
  $SQL = "SELECT ID FROM users ";
  $SQL .= "WHERE name=:name ";

  $statement = $dbh->prepare($SQL);
  $statement->bindParam(":name",$uname,PDO::PARAM_STR);
  $statement->execute();

  $return = $statement->fetchColumn();

  return $return;
}

function lookup_uname_by_email($email) {

  global $dbh;
  
  $SQL = "SELECT name FROM users ";
  $SQL .= "WHERE email=:email ";

  $statement = $dbh->prepare($SQL);
  $statement->bindParam(":email",$email,PDO::PARAM_STR);
  $statement->execute();

  return $statement->fetchColumn();

}

function lookup_user($uid) {

  global $dbh;
  
  $SQL = "SELECT * FROM users ";
  $SQL .= "WHERE ID=:uid ";

  $statement = $dbh->prepare($SQL);
  $statement->bindParam(":uid",$uid,PDO::PARAM_INT);
  $statement->execute();

  return $statement->fetch(PDO::FETCH_ASSOC);
  
}


function lookup_user_by_name($uname) {

  global $dbh;
  
  $SQL = "SELECT * FROM users ";
  $SQL .= "WHERE name=:name ";

  $statement = $dbh->prepare($SQL);
  $statement->bindParam(":name",$uname,PDO::PARAM_STR);
  $statement->execute();

  return $statement->fetch(PDO::FETCH_ASSOC);
  
}

## IV.A.1 Account Type Changes

function basic_to_premium($uid) {

  if (is_premium($uid)) {
    return;
  }

  $userInfo = lookup_user($uid);
  $now = time();
  $secondsLeft = $userInfo['next_stamp'] - $now;

  if ($secondsLeft <= 0) {
    $secondsLeft = 0;
  }

  $paypalCF = read_config("financial.json");
  $secondsLeft = floor($secondsLeft / $paypalCF['premiumToBasicConversion']);

  $newStamp = time() + $secondsLeft;

  $newDate = getdate($newStamp);
  $newMonth = $newDate['mon'];
  $newYear = $newDate['year'];
  $newDay = $newDate['mday'];

  $SQL = "UPDATE users ";
  $SQL .= "SET next_year=:ny,next_month=:nm,pay_day=:nd,next_stamp=:ns ";
  $SQL .= "WHERE ID=:uid ";

  global $dbh;
  $statement = $dbh->prepare($SQL);
  $statement->bindParam(":ny",$newYear,PDO::PARAM_INT);
  $statement->bindParam(":nm",$newMonth,PDO::PARAM_INT);
  $statement->bindParam(":nd",$newDay,PDO::PARAM_INT);
  $statement->bindParam(":ns",$newStamp,PDO::PARAM_INT);
  $statement->bindParam(":uid",$uid,PDO::PARAM_INT);
  $statement->execute();

  set_flag_for_user($uid,"premium");
  
}

## IV.A.2 User Bans

function delete_ban($uid) {

  delete_flag_sub($uid,"banned");
  return delete_ban_sub($uid);
  
}

function delete_ban_sub($uid) {

  global $dbh;
  
  $SQL = "DELETE FROM bans ";
  $SQL .= "WHERE ID=:id";

  $statement = $dbh->prepare($SQL);
  $statement->bindParam(":id",$uid,PDO::PARAM_INT);
  return $statement->execute();

}

function is_banned($uid) {

  global $dbh;
  
  $SQL = "SELECT * FROM bans ";
  $SQL .= "WHERE ID=:uid ";

  $statement = $dbh->prepare($SQL);
  $statement->bindParam(":uid",$uid,PDO::PARAM_INT);
  $statement->execute();

  return $statement->rowCount();
  
}


function query_baninfo($uid) {

  global $dbh;
  
  $SQL = "SELECT * FROM bans ";
  $SQL .= "WHERE ID=:id";

  $statement = $dbh->prepare($SQL);
  $statement->bindParam(":id",$uid,PDO::PARAM_INT);
  $statement->execute();

  return $statement->fetch(PDO::FETCH_ASSOC);
  
}

function set_ban($uid,$reason,$who) {

  set_flag_for_user($uid,"banned");
  return set_ban_sub($uid,$reason,$who);
  
}

function set_ban_sub($uid,$reason,$who) {

  global $dbh;

  $SQL = "DELETE FROM bans WHERE ID=:uid";
  $statement = $dbh->prepare($SQL);
  $statement->bindParam(":uid",$uid,PDO::PARAM_INT);
  $statement->execute();
    
  $SQL = "INSERT INTO bans (ID, ban_when, ban_reason, ban_who) ";
  $SQL .= "VALUES (:uid, :when, :reason, :who) ";

  $statement = $dbh->prepare($SQL);
  $statement->bindParam(":uid",$uid,PDO::PARAM_INT);
  $when = time();    
  $statement->bindParam(":when",$when,PDO::PARAM_INT);
  $statement->bindParam(":reason",$reason,PDO::PARAM_STR);
  $statement->bindParam(":who",$who,PDO::PARAM_STR);        
  return $statement->execute();
    
  return TRUE;
  
}  

function update_user_value($uid,$property,$value) {

  global $dbh;
  
  $SQL = "DESCRIBE users";

  $statement = $dbh->prepare($SQL);
  $statement->execute();    

  $keys = $statement->fetchAll(PDO::FETCH_COLUMN);

  if (!in_array($property,$keys)) {
    return FALSE;
  } else if ($property == "ID" || $property == "name") {
    return FALSE;
  } else if ($property == "next_stamp") {

    $newDate = getdate($value);
    $newMonth = $newDate['mon'];
    $newYear = $newDate['year'];
    $newDay = $newDate['mday'];

    $SQL = "UPDATE users ";
    $SQL .= "SET next_year=:ny,next_month=:nm,pay_day=:nd,next_stamp=:ns ";
    $SQL .= "WHERE ID=:uid ";

    global $dbh;
    $statement = $dbh->prepare($SQL);
    $statement->bindParam(":ny",$newYear,PDO::PARAM_INT);
    $statement->bindParam(":nm",$newMonth,PDO::PARAM_INT);
    $statement->bindParam(":nd",$newDay,PDO::PARAM_INT);
    $statement->bindParam(":ns",$value,PDO::PARAM_INT);
    $statement->bindParam(":uid",$uid,PDO::PARAM_INT);
    return $statement->execute();

  } else {

    $SQL = "UPDATE users ";
    $SQL .= "SET $property=:value ";
    $SQL .= "WHERE ID=:uid ";

    $statement = $dbh->prepare($SQL);
    $statement->bindParam(":value",$value,PDO::PARAM_INT);    
    $statement->bindParam(":uid",$uid,PDO::PARAM_INT);
    return $statement->execute();

  }
    
}

## IV.A.3 Payments

function bump_months($uid,$months) {

  global $dbh;
  
  $SQL = "SELECT next_month, next_year, pay_day, next_stamp FROM users ";
  $SQL .= "WHERE ID=:uid ";

  $statement = $dbh->prepare($SQL);
  $statement->bindParam(":uid",$uid,PDO::PARAM_INT);
  $statement->execute();

  $nextInfo = $statement->fetch(PDO::FETCH_ASSOC);

  $nextMonth = $nextInfo['next_month'];
  $nextYear = $nextInfo['next_year'];
  $nextDay = $nextInfo['pay_day'];
  $nextStamp = $nextInfo['next_stamp'];

  if ($nextStamp < time()) {

    $thisStamp = getdate();
    $nextMonth = $thisStamp['mon'];
    $nextYear = $thisStamp['year'];
    $nextDay = $thisStamp['mday'];
    
  }

  $nextMonth += $months;

  while ($nextMonth > 12) {
    $nextYear++;
    $nextMonth -= 12;
  }

  while ($nextMonth < 0) {
    $nextYear--;
    $nextMonth += 12;
  }

  $nextStamp = strtotime("$nextMonth/$nextDay/$nextYear");
  
  $SQL = "UPDATE users ";
  $SQL .= "SET next_year=:ny,next_month=:nm,pay_day=:nd,next_stamp=:ns ";
  $SQL .= "WHERE ID=:uid ";

  $statement = $dbh->prepare($SQL);
  $statement->bindParam(":ny",$nextYear,PDO::PARAM_INT);
  $statement->bindParam(":nm",$nextMonth,PDO::PARAM_INT);
  $statement->bindParam(":nd",$nextDay,PDO::PARAM_INT);
  $statement->bindParam(":ns",$nextStamp,PDO::PARAM_INT);      
  $statement->bindParam(":uid",$uid,PDO::PARAM_INT);
  $statement->execute();

  
}

function delete_pending($uid) {

  global $dbh;

  $SQL = "UPDATE purchases ";
  $SQL .= "SET pending=0 ";
  $SQL .= "WHERE ID=:uid ";

  $statement = $dbh->prepare($SQL);
  $statement->bindParam(":uid",$uid,PDO::PARAM_INT);
  return $statement->execute();

}

function is_paid($uid) {

  global $dbh;

  $SQL = "SELECT account_type, next_stamp FROM users ";
  $SQL .= "WHERE ID=:uid ";

  $statement = $dbh->prepare($SQL);
  $statement->bindParam(":uid",$uid,PDO::PARAM_INT);
  $statement->execute();

  $paymentInfo = $statement->fetch(PDO::FETCH_ASSOC);

  if ($paymentInfo['account_type'] == "developer" ||
      $paymentInfo['account_type'] == "staff" ||
      $paymentInfo['account_type'] == "free") {

      return 1;

   } else {

      return ($paymentInfo['next_stamp'] >= time());

   }



}

function is_pending($uid) {

  global $dbh;

  $SQL = "SELECT COUNT(*) FROM purchases ";
  $SQL .= "WHERE ID=:uid ";
  $SQL .= "AND pending=1 ";

  $statement = $dbh->prepare($SQL);
  $statement->bindParam(":uid",$uid,PDO::PARAM_INT);
  $statement->execute();

  return $statement->fetchColumn();
  
}


function log_purchase($uid,$type,$amt,$cost,$pending) {

  global $dbh;

  if ($type == "regular") {
    $type = "basic";
  }
  
  $SQL = "INSERT INTO purchases ";
  $SQL .= "(ID, purchasedate, purchasetype, purchaseamt, purchasecost, pending) ";
  $SQL .= "VALUES (:uid, :date, :type, :amt, :cost, :pending) ";

  $now = date('Y-m-d');

  $statement = $dbh->prepare($SQL);
  $statement->bindParam(":uid",$uid,PDO::PARAM_INT);
  $statement->bindParam(":date",$now,PDO::PARAM_STR);
  $statement->bindParam(":type",$type,PDO::PARAM_STR);
  $statement->bindParam(":amt",$amt,PDO::PARAM_INT);
  $statement->bindParam(":cost",$cost,PDO::PARAM_STR);
  $statement->bindParam(":pending",$pending,PDO::PARAM_INT);
  
  return $statement->execute();

}


## IV.A.3B Paypal

function is_paypal_txnid_used($txnid) {

  global $dbh;
  
  $SQL = "SELECT COUNT(*) FROM paypal_txs WHERE txnid=:txnid";

  $statement = $dbh->prepare($SQL);
  $statement->bindParam(":txnid",$txnid,PDO::PARAM_INT);
  $statement->execute();
  
  return $statement->fetchColumn();

}

function update_paypal_data($uid,$txnid,$item_name,$payment_amount,$payer_email) {

  global $dbh;
  
  $SQL = "INSERT into paypal_txs ";
  $SQL .= "(txnid,ID,itemname,payment,email) ";
  $SQL .= "VALUES (:txnid,:ID,:item_name,:payment_amount,:payer_email) ";

  $statement = $dbh->prepare($SQL);
  $statement->bindParam(":txnid",$txnid,PDO::PARAM_INT);   
  $statement->bindParam(":ID",$uid,PDO::PARAM_INT);
  $statement->bindParam(":item_name",$item_name,PDO::PARAM_STR);
  $statement->bindParam(":payment_amount",$payment_amount,PDO::PARAM_STR);
  $statement->bindParam(":payer_email",$payer_email,PDO::PARAM_STR);
  
  $return = $statement->execute();

  return $return;
}

## IV.A.4 Email

function delete_ping($uid) {

  global $dbh;

  $SQL = "DELETE FROM email_ping ";
  $SQL .= "WHERE ID=:uid";

  $statement = $dbh->prepare($SQL);
  $statement->bindParam(":uid",$uid,PDO::PARAM_INT);
  return $statement->execute();
  
}

function has_changed_email($uid) {

  global $dbh;
  
  $SQL = "SELECT email FROM email_ping ";
  $SQL .= "WHERE ID=:uid";

  $statement = $dbh->prepare($SQL);
  $statement->bindParam(":uid",$uid,PDO::PARAM_INT);
  $statement->execute();

  $new_email = $statement->fetchColumn();

  if (!$new_email) {
    return FALSE;
  }
  
  $old_email = lookup_email($uid);

  return ($new_email != $old_email);

}


function has_verified_email($uid) {

  global $dbh;

  $SQL = "SELECT COUNT(*) FROM email_ping ";
  $SQL .= "WHERE ID=:uid";

  $statement = $dbh->prepare($SQL);
  $statement->bindParam(":uid",$uid,PDO::PARAM_INT);
  $statement->execute();

  $unverified = $statement->fetchColumn();

  if ($unverified) {
    return FALSE;
  } else {
    return TRUE;
  }
}

function ping_user($uid,$email) {

  global $dbh;

  $SQL = "DELETE FROM email_ping ";
  $SQL .= "WHERE ID=:uid";

  $statement = $dbh->prepare($SQL);
  $statement->bindParam(":uid",$uid,PDO::PARAM_INT);
  $statement->execute();

  $SQL = "INSERT INTO email_ping ";
  $SQL .= "(ID,email,stamp,code) ";
  $SQL .= "VALUES (:uid, :email, :stamp, :code) ";

  $statement = $dbh->prepare($SQL);
  $statement->bindParam(":uid",$uid,PDO::PARAM_INT);
  $statement->bindParam(":email",$email,PDO::PARAM_STR);

  $now = time();
  $statement->bindParam(":stamp",$now,PDO::PARAM_STR);

  $code = bin2hex(random_bytes(10));
  $statement->bindParam(":code",$code,PDO::PARAM_STR);

  $return = $statement->execute();  

  return $return;
  
}

function mail_ping($uid) {

  global $dbh;

  $pingInfo = query_pinginfo($uid);
  $userInfo = lookup_user($uid);
  $siteInfo = read_config("general.json");
  
  $mailBody = $userInfo['name'] . ",\n\n";
  
  $mailBody .= "Please verify your email address as " . $pingInfo['email'] . " by clicking on the following link:\n\n ";

  $mailBody .= "https://" . $siteInfo['userdbURL'] . "/verify-email.php?email-code=" . $pingInfo['code'] . "\n\n";

  $mailBody .= "The " . $siteInfo['siteName'] . " Team\n";
  
  sendEmailMessage($userInfo['name'], $pingInfo['email'], $siteInfo['siteName'] . " Email Verification", $mailBody, $complaint);

  if ($complaint) {
    return FALSE;
  } else {
    return TRUE;
  }
  
}

function query_pinginfo($uid) {

  global $dbh;

  $SQL = "SELECT * from email_ping ";
  $SQL .= "WHERE ID=:uid ";

  $statement = $dbh->prepare($SQL);
  $statement->bindParam(":uid",$uid,PDO::PARAM_INT);
  $statement->execute();

  return $statement->fetch(PDO::FETCH_ASSOC);
  
}

## IV.B. Properties

function bill_log($uid) {

  $SQL = "SELECT * FROM purchases WHERE ID=:uid ORDER BY purchasedate DESC";

  global $dbh;
  $statement = $dbh->prepare($SQL);
  $statement->bindParam(":uid",$uid,PDO::PARAM_INT);
  $statement->execute();

  $status = array();
  while ($thisResult = $statement->fetch(PDO::FETCH_ASSOC)) {
    $billing[] = $thisResult;
  }

  return $billing;
}

function query_bill_status($uid) {

  $a_type = query_property($uid,"account_type");
  
  if ($a_type == "free" || $a_type == "developer" || $a_type == "staff") {
  
    return "free";
    
  } else {
  
    $expiration = query_next_stamp($uid);
    if ($expiration > time()) {

      return $a_type;

    } else {
			   
      if (query_flag($uid,"no-email")) {

        return "lost";

      } else {

         return "gone";

      }
    }
  }
  return "unknown";
  
}

Function delete_property($uid,$property) {

# TODO: Add userinfo and stuff? Only if needed

  if (in_array($property,list_flags())) {
    return delete_flag($uid,$property);
  } else if ($property == "pendpaid") {
    delete_pending($uid);
  } else {
    return delete_access($uid,$game);
  }

}

function is_premium($uid) {

  return query_flag($uid,"premium");

}

function query_next_stamp($uid) {

  $userinfo = lookup_user($uid);

  if ($userinfo['account_type'] == "free" ||
      $userinfo['account_type'] == "developer" ||
      $userinfo['account_type'] == "staff") {

        return (time() + (30 * 86400));

  } else {

    return $userinfo['next_stamp'];

  }

}

function query_property($uid,$property) {

# Rewrite some old properties to make main function more rational

# Effectively:
# case "banned"
# case "deleted"
# case "grand"
# case "no-email"
# case "premium"
# case "terms-of-service"

  if (in_array($property,list_flags())) {
    return query_flag($uid,$property);
  } else {
  
    switch ($property) {

  	 case "payday":
           $property = "pay_day";
           break;	     
  	 case "nextstamp":
           $property = "next_stamp";
           break;
  }


# Check the properties

  switch ($property) {

# Defunct Properties

  	  case "account_credit_potential":
	  case "billinglog:public":
  	  case "card":
	  case "card:expired":
	  case "cost":
	  case "dayth":
	  case "eighteen":
	  case "month":
	  case "monthlycost":
	  case "monthlyservice":
	  case "motd:global:expire":
	  case "motd:global:message":
	  case "motd:global:timestamp":
	  case "password:crypt":
	  case "password:md5":
	  case "payment_status":
	  case "quarterlycost":
	  case "quarterlyservice":
	  case "royalties:available":
	  case "royalties:name1":
	  case "royalties:position1":
	  case "royalties:name2":
	  case "royalties:position2":
	  case "royalties:name3":
	  case "royalties:position3":	  	  
  	  case "salefailure":
	  case "salefailures":
	  case "sixteen-waiver":
          case "storypoints:eligible":
	  case "year":
	  case "yearlycost":
	  case "yearlyservice":
	  
	    return 0;

# TODO: IS THIS NEEDED?

	  case "idents":

	    return 0;
# Active Properties

  	 case "account_credit":
  	 case "account_type":
	 case "creation_time";
  	 case "email":
	 case "ID":
	 case "name":
	 case "next_stamp":
	 case "pay_day":

	    $userinfo = lookup_user($uid);
	    return $userinfo[$property];

	 case "account_status":

            $status = query_all_status($uid);
	    return implode(",",$status);

	 case "billing_status":

	   return query_bill_status($uid);
	   
	 case "trialdays":

	    $userinfo = lookup_user($uid);
	    if ($userinfo['account_type'] == "trial") {
	    
	      $expiration = query_next_stamp($uid);
	      if ($expiration > time()) {
	        return ceil(($expiration - time()) / 86400);
	      }
	    }
	    return 0;

          case "paiddays":

	    $userinfo = lookup_user($uid);
            if ($userinfo['account_type'] == "free" ||
              $userinfo['account_type'] == "developer" ||
              $userinfo['account_type'] == "staff") {

	        return 30;

            } else if ($userinfo['account_type'] == "trial") {

	      return 0;

	    } else {

  	      $expiration = query_next_stamp($uid);
	      if ($expiration > time()) {
	        return ceil(($expiration - time()) / 86400);
	      } else {
	        return 0;
              }
            }

          case "pendpaid":

            return is_pending($uid);

	  case "billinglog":

	    $billing = bill_log($uid);

	    if ($billing) {

	      $billReturn = "";
  	      for ($i = 0 ; $i < sizeof($billing) ; $i++) {

	        switch ($billing[$i]['purchasetype']) {

		  case "convert-to-basic":

		    $billDesc = "Converted to Basic Account";
		    break;

		  case "convert-to-premium":

		    $billDesc = "Converted to Premium Account";
		    break;

		  case "basic":

		    $billDesc = "Subscribed for " . $billing[$i]['purchaseamt'] . " Basic Month";
		    if ($billing[$i]['purchaseamt'] > 1) {
		      $billDesc .= "s";
		    }
		    break;

		  case "premium":

		    $billDesc = "Subscribed for " . $billing[$i]['purchaseamt'] . " Premium Month";
		    if ($billing[$i]['purchaseamt'] > 1) {
		      $billDesc .= "s";
		    }
		    break;
		    
		  case "sps":

		    $billDesc = "Purchased " . $billing[$i]['purchaseamt'] . " StoryPoints";
		    break;

		  default:

		    $billDesc = "Unknown billing event (quantity: " . $billing[$i]['purchaseamt'] . ")";
		    break;

		}
		
	        $billReturn .= $billing[$i]['purchasedate'] . "  " . $billDesc;
		if ($billing[$i]['purchasecost']) {
		  $billReturn .= " ($" . number_format($billing[$i]['purchasecost'],2) . ")";
		}
		$billReturn .= "\n";
	      }

	      return $billReturn;
	      
	    } else {
              return "";
	    }
          case "storypoints:total":

            return sps_total($uid);

          case "storypoints:used":

            return sps_used($uid);

          case "storypoints:available":

            return sps_available($uid);
 

	  case "banned:reason":
	  case "banned:who":
	  case "banned:when":

            $baninfo = query_baninfo($uid);

	    if ($property == "banned:reason") {
	    
	      return $baninfo['ban_reason'];

	    } else if ($property == "banned:when") {

	      return $baninfo['ban_when'];

	    } else if ($property == "banned:who") {

	      return $baninfo['ban_who'];

	    }

	  case "email-ping:code":

	    $pinginfo = query_pinginfo($uid);
	    return $pinginfo['code'];
	    
	  case "email-ping:stamp":

	    $pinginfo = query_pinginfo($uid);
	    return $pinginfo['stamp'];
    }
  }
}

function set_property($uid,$property,$value) {

# Rewrite some old properties to make main function more rational

# Effectively:
# case "banned"
# case "deleted"
# case "grand"
# case "no-email"
# case "premium"
# case "terms-of-service"

  if (in_array($property,list_flags())) {
    return set_flag_for_user($uid,$property);
  } else {
  
    switch ($property) {

  	 case "payday":
           $property = "pay_day";
           break;	     
  	 case "nextstamp":
           $property = "next_stamp";
           break;
  }


# Check the properties

  switch ($property) {

# Defunct Properties

  	  case "account_credit_potential":
  	  case "billinglog":
	  case "billinglog:public":
  	  case "card":
	  case "card:expired":
	  case "cost":
	  case "dayth":
	  case "eighteen":
	  case "email-ping:stamp":
	  case "month":
	  case "monthlycost":
	  case "monthlyservice":
	  case "motd:global:expire":
	  case "motd:global:message":
	  case "motd:global:timestamp":
	  case "password:crypt":
	  case "password:md5":
	  case "payment_status":
	  case "quarterlycost":
	  case "quarterlyservice":
	  case "royalties:available":
	  case "royalties:name1":
	  case "royalties:position1":
	  case "royalties:name2":
	  case "royalties:position2":
	  case "royalties:name3":
	  case "royalties:position3":	  	  
  	  case "salefailure":
	  case "salefailures":
	  case "sixteen-waiver":
          case "storypoints:eligible":
	  case "year":
	  case "yearlycost":
	  case "yearlyservice":
	  
	    return 0;

# TODO: IS THIS NEEDED?

	  case "idents":

	    return 0;
# Active Properties

  	 case "account_credit":
  	 case "account_type":
  	 case "email":
	 case "name":
	 case "next_stamp":
	 case "pay_day":

            return update_user_value($uid,$property,$value);

	 case "next_stamp":

	      return query_next_stamp($uid);
	      
	 case "password":

	      return get_password_for_user($uid);
    }	      
  }
}


## IV.B.1 Status = Access + Flag

function query_all_status($uid) {

  $SQL = "SELECT * FROM flags ";
  $SQL .= "WHERE ID=:uid ";

  global $dbh;
  $statement = $dbh->prepare($SQL);
  $statement->bindParam(":uid",$uid,PDO::PARAM_INT);
  $statement->execute();

  $status = array();
  while ($thisResult = $statement->fetch(PDO::FETCH_ASSOC)) {
    $status[] = $thisResult['flag'];
   }

  $SQL = "SELECT * FROM access ";
  $SQL .= "WHERE ID=:uid ";

  $statement = $dbh->prepare($SQL);
  $statement->bindParam(":uid",$uid,PDO::PARAM_INT);
  $statement->execute();

  while ($thisResult = $statement->fetch(PDO::FETCH_ASSOC)) {
    $status[] = $thisResult['game'];
  }

  return $status;
}

## IV.B.2 Access

function delete_access($uid,$game) {

  $SQL = "DELETE FROM access ";
  $SQL .= "WHERE ID=:id ";
  $SQL .= "AND game=:game ";

  global $dbh;
  $statement = $dbh->prepare($SQL);
  $statement->bindParam(":id",$uid,PDO::PARAM_INT);
  $statement->bindParam(":game",$game,PDO::PARAM_STR);
  return $statement->execute();

}

function query_access($uid,$flag) {

  global $dbh;

  $SQL = "SELECT * FROM flags ";
  $SQL .= "WHERE ID=:id ";
  $SQL .= "AND game=:flag ";

  $statement = $dbh->prepare($SQL);
  $statement->bindParam(":id",$uid,PDO::PARAM_INT);
  $statement->bindParam(":flag",$flag,PDO::PARAM_STR);
  $statement->execute();

  return $statement->rowCount();

}

function set_access_for_user($uid,$flag) {

  if (query_access($uid,$flag)) {

    return TRUE;
    
  } else {
  
    global $dbh;

    $SQL = "INSERT INTO access (ID, game) ";
    $SQL .= "VALUES (:uid, :game) ";

    $statement = $dbh->prepare($SQL);
    $statement->bindParam(":uid",$uid,PDO::PARAM_INT);
    $statement->bindParam(":game",$flag,PDO::PARAM_STR);
    return $statement->execute();

   }

}


## IV.B.3 Flag

function account_with_flag($flag) {

  $SQL = "SELECT name FROM users, flags ";
  $SQL .= "WHERE users.ID=flags.ID ";
  $SQL .= "AND flags.flag=:flag ";
  $SQL .= "AND (users.next_stamp>=:now ";
  $SQL .= "OR users.account_type='free' ";
  $SQL .= "OR users.account_type='staff' ";
  $SQL .= "OR users.account_type='developer') ";
  $SQL .= "ORDER BY name ";

  global $dbh;
  $statement = $dbh->prepare($SQL);
  $now = time();
  $statement->bindParam(":flag",$flag,PDO::PARAM_STR);
  $statement->bindParam(":now",$now,PDO::PARAM_INT);  
  $statement->execute();

  print_r($statement->errorInfo());
  $people = array();
  while ($thisResult = $statement->fetchColumn()) {
    $people[] = $thisResult;
  }

  return $people;
  
}

function delete_flag($uid,$flag) {

  if ($flag == "banned") {
    delete_ban_sub($uid);
  }
  return delete_flag_sub($uid,$flag);
  
}

function delete_flag_sub($uid,$flag) {

  $SQL = "DELETE FROM flags ";
  $SQL .= "WHERE ID=:id ";
  $SQL .= "AND flag=:flag ";

  global $dbh;
  $statement = $dbh->prepare($SQL);
  $statement->bindParam(":id",$uid,PDO::PARAM_INT);
  $statement->bindParam(":flag",$flag,PDO::PARAM_STR);
  $return = $statement->execute();

  return $return;
}

function is_deleted($uid) {

  return query_flag($uid,"deleted");

}

function is_tossed($uid) {

  return query_flag($uid,"terms-of-service");

}
  

function list_flags() {

  global $dbh;
  
  $SQL = "SELECT DISTINCT flag FROM flags ";
  $SQL .= "GROUP BY flag ORDER BY flag ";
  
  $statement = $dbh->prepare($SQL);
  $statement->execute();

  return $statement->fetchAll(PDO::FETCH_COLUMN);
  
}

function query_flag($uid,$flag) {

  global $dbh;

  $SQL = "SELECT * FROM flags ";
  $SQL .= "WHERE ID=:id ";
  $SQL .= "AND flag=:flag ";

  $statement = $dbh->prepare($SQL);
  $statement->bindParam(":id",$uid,PDO::PARAM_INT);
  $statement->bindParam(":flag",$flag,PDO::PARAM_STR);
  $statement->execute();

  $return = $statement->rowCount();

  return $return;
  
}

function set_flag_for_user($uid,$flag) {

  if ($flag == "banned") {
    set_ban_sub($uid,"Unknown Reason","Set Flag Command");
  }
  return set_flag_for_user_sub($uid,$flag);
}

  
function set_flag_for_user_sub($uid,$flag) {

  if (query_flag($uid,$flag)) {
    return TRUE;
  } else {

    global $dbh;

    $SQL = "INSERT INTO flags (ID, flag) ";
    $SQL .= "VALUES (:uid, :flag) ";

    $statement = $dbh->prepare($SQL);
    $statement->bindParam(":uid",$uid,PDO::PARAM_INT);
    $statement->bindParam(":flag",$flag,PDO::PARAM_STR);
    return $statement->execute();
    
   }
   
}

## IV.C: Story Points

function sps_purchased_recent($uid,$length = 0) {

  global $dbh;

  if (!$length) {
    $lengthWord = "1 MONTH";
  } else {
    $lengthWord = "$length DAY";
  }

  $SQL = "SELECT SUM(purchaseamt) FROM purchases ";
  $SQL .= "WHERE purchasedate BETWEEN SUBDATE(CURDATE(), INTERVAL $lengthWord) AND NOW() ";
  $SQL .= "AND ID=:id ";
  $SQL .= "AND purchasetype='sps' ";

  $statement = $dbh->prepare($SQL);
  $statement->bindParam(":id",$uid,PDO::PARAM_INT);
  $statement->execute();
  $return = $statement->fetchColumn();

  return $return;
}

function sps_add($uid,$sps,$reason,$from,$comment) {

  global $dbh;

  $SQL = "INSERT INTO storypoints (ID,sp_value,sp_date,sp_reason,sp_comment,sp_who) ";
  $SQL .= "VALUES (:uid,:value,:date,:reason,:comment,:who) ";
  
  $statement = $dbh->prepare($SQL);
  $statement->bindParam(":uid",$uid,PDO::PARAM_INT);
  $statement->bindParam(":value",$sps,PDO::PARAM_INT);

  $date = date("M/d/Y",time());
  $statement->bindParam(":date",$date,PDO::PARAM_INT);
  $statement->bindParam(":reason",$reason,PDO::PARAM_STR);
  $statement->bindParam(":comment",$comment,PDO::PARAM_STR);
  $statement->bindParam(":who",$from,PDO::PARAM_STR);

  return $statement->execute();
    
}

function sps_log($uid) {

  global $dbh;
  
  $SQL = "SELECT * FROM storypoints ";
  $SQL .= "WHERE ID=:uid ";

  $statement = $dbh->prepare($SQL);
  $statement->bindParam(":uid",$uid,PDO::PARAM_INT);
  $statement->execute();

  $sps = array();
  while ($thisResult = $statement->fetch(PDO::FETCH_ASSOC)) {
    $sps[] = $thisResult;
  }
  return $sps;
  
}

function sps_total($uid) {

  global $dbh;

  $SQL = "SELECT SUM(sp_value) FROM storypoints ";
  $SQL .= "WHERE ID=:uid ";
  $SQL .= "AND sp_value > 0";

  $statement = $dbh->prepare($SQL);
  $statement->bindParam(":uid",$uid,PDO::PARAM_INT);
  $statement->execute();

  $total = $statement->fetch(PDO::FETCH_NUM);

  return $total[0];

}

function sps_used($uid) {

  global $dbh;

  $SQL = "SELECT SUM(sp_value) FROM storypoints ";
  $SQL .= "WHERE ID=:uid ";
  $SQL .= "AND sp_value < 0";

  $statement = $dbh->prepare($SQL);
  $statement->bindParam(":uid",$uid,PDO::PARAM_INT);
  $statement->execute();

  $total = $statement->fetch(PDO::FETCH_NUM);

  return $total[0];

}

function sps_available($uid) {

  global $dbh;

  $SQL = "SELECT SUM(sp_value) FROM storypoints ";
  $SQL .= "WHERE ID=:uid ";

  $statement = $dbh->prepare($SQL);
  $statement->bindParam(":uid",$uid,PDO::PARAM_INT);
  $statement->execute();

  $total = $statement->fetch(PDO::FETCH_NUM);

  return $total[0];

}

## IV.D: Play

function log_play($uid, $start, $time) {

  global $dbh;
  
  $SQL = "INSERT INTO plays (ID, stamp, duration) ";
  $SQL .= "VALUES (:uid, :start, :duration) ";

  $statement = $dbh->prepare($SQL);
  $statement->bindParam(":uid",$uid,PDO::PARAM_INT);
  $statement->bindParam(":start",$start,PDO::PARAM_INT);
  $statement->bindParam(":duration",$time,PDO::PARAM_INT);  
  $statement->execute();

}


## V. Administrivia

function sendEmailMessage($name, $email, $subject, $message, &$complaint) {

    $smtp_sock = fsockopen("127.0.0.1", 25, $errno, $errstr, 30);
    if (!$smtp_sock) {
        $complaint = "There was a technical problem. CONNECTING.  Please try again later.";
        return FALSE;
    }

    $configInfo = read_config("general.json");
    $supportEmail = $configInfo['supportEmail'];
    $gameName = $configInfo['siteName'];
    
    $result = fgets($smtp_sock, 2048);
    if (substr($result, 0, 1) != "2") {
        $complaint = "There was a technical problem.  RETRIEVING. Please try again later.";
        return FALSE;
    }
    fputs($smtp_sock, "HELO localhost\r\n");
    $result = fgets($smtp_sock, 2048);
    if (substr($result, 0, 1) != "2") {
        $complaint = "There was a technical problem.  HELO. Please try again later.";
        return FALSE;
    }
    fputs($smtp_sock, "MAIL FROM: <$supportEmail>\r\n");
    $result = fgets($smtp_sock, 2048);
    if (substr($result, 0, 1) != "2") {
        $complaint = "There was a technical problem.  FROM $supportEmail. Please try again later.";
        return FALSE;
    }
    fputs($smtp_sock, "RCPT TO: <$email>\r\n");
    $result = fgets($smtp_sock, 2048);
    if (substr($result, 0, 1) != "2") {
        $complaint = "There was a technical problem.  TO $email. Please try again later.";
        return FALSE;
    }
    fputs($smtp_sock, "DATA\r\n");
    $result = fgets($smtp_sock, 2048);
    if (substr($result, 0, 1) != "3") {
        $complaint = "There was a technical problem.  DATA. Please try again later.";
        return FALSE;
    }
    fputs($smtp_sock, "From: $gameName <$supportEmail>\r\n");
    fputs($smtp_sock, "To: $name <$email>\r\n");
    fputs($smtp_sock, "Subject: $subject\r\n");
    fputs($smtp_sock, "\r\n");
    fputs($smtp_sock, $message);
    fputs($smtp_sock, ".\r\n");
    $result = fgets($smtp_sock, 2048);
    if (substr($result, 0, 1) != "2") {
        $complaint = "There was a technical problem. CONTENT. Please try again later.";
	$complaint .= "!!$result";
        return FALSE;
    }
    fputs($smtp_sock, "QUIT\r\n");
    return TRUE;
}




## REWRITING


## OLD STUFF

/*
**	Return TRUE if redirection has occured; this means the
**	calling script will (should) exit immediately. You may
**	return FALSE to indicate more work is needed: usually,
**	there is some problem with the form. In this case, the
**	$complaint variable should be set.
*/

function searchOnField(&$ctl_sock, $field, $pattern, &$complaint) {
    if (!connect_ctl_sock($ctl_sock, $complaint)) {
       return FALSE;
    }
    fputs($ctl_sock, "search 1 " . $field . " " . urlencode($pattern) . "\n");
    $result = fgets($ctl_sock, 2048);
    if (substr($result, 2, 2) == "OK") {
        $result = chop(substr($result, 5));
        if (strlen($result) > 0) {
            $list = explode(" ", $result);
            return $list;
        }
        return array();
    }
    $complaint = "UserDB error: " . $problem;
    return FALSE;
}
?>
