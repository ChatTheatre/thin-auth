<?

require_once("userdb.php");
$config = read_config("general.json");
$siteName = $config['siteName'];

# Not logged in? GOTO Login page.

# In userdb.php

  $user = $_COOKIE["user"];
  $pass = $_COOKIE["pass"];
  $uid = getNewProperty($auth_sock, $user, $pass, $complaint, "ID");

  $email_code = $_GET['email-code'];
  
  if ($user == "" || checkTimeOut($auth_sock, $user, $pass, $complaint)) {
     Header("Location: login.php?email-code=$email_code&timeout=1&timeout_from=" .
	    urlencode($_SERVER['PHP_SELF'] . ($_SERVER['QUERY_STRING'] ? "?" . $_SERVER['QUERY_STRING'] : "")));
     exit;
  }

  $complaint = "";

  getPing($auth_sock,$user,$pass,$pinginfo,$complaint);

  if (!$pinginfo || $complaint == "NO PING") {
    Header("Location: overview.php");
    exit;
  }
  
  if ($pinginfo['ID'] != $uid) {
    $complaint = "This is the wrong account for that email update! You may wish to <a href='logout.php'>logout</a>.";

    
  } else if ($pinginfo['code'] != $email_code) {
    $complaint = "This email update is out-of-date. Please <a href='change-email.phtml'>try again</a>.";
  } else {

    deletePing($ctl_sock,$user,TRUE,$complaint);
    $message = "Your email has been successfully updated to " . $pinginfo['email'] . "<br><br>Continue to <a href='overview.php'>overview</a>.";
    
  }
  
?>

<html>
<head>
<link rel="stylesheet" href="assets/login.css">
</head>
<body>

<table class="center">
  <tr>
    <td>
<div class='acctinfo'>
  <div class='titlebar'>
    Verify New <? echo $siteName; ?> Email
  </div>
  
<? if ($complaint) {

  echo "<p><font color='red'>Error:</font></b> $complaint";

    } else if ($message) {

  echo "<p><i>$message</i>";
  
    } ?>
  </div>
    </td>
  </tr>
</table>
</body>
</html>
