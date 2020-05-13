<?

require_once("userdb.php");
$config = read_config("general.json");
$siteName = $config['siteName'];

# In userdb.php

  $user = $_COOKIE["user"];
  $pass = $_COOKIE["pass"];

  if ($user == "" || checkTimeOut($auth_sock, $user, $pass, $complaint)) {
     Header("Location: login.php?email-code=$email_code&timeout=1&timeout_from=" .
	    urlencode($_SERVER['PHP_SELF'] . ($_SERVER['QUERY_STRING'] ? "?" . $_SERVER['QUERY_STRING'] : "")));
     exit;
  }

  $id = $_GET['id'];
  $isAdmin = isAdmin($auth_sock,$user,$pass,$complaint);

  if ($id && $isAdmin) {
    $thisUser = urldecode($id);
  } else {
    $thisUser = $user;
  }

  $complaint = "";
  playing_history($ctl_sock, $thisUser,0, $play_log, $complaint);

  if (sizeof($play_log) < 2 && !$complaint) {
    if ($id  && $isAdmin) {
      $complaint = "$id has no play log!";    
    } else {
      $complaint = "You have no play log!";
    }  
  }
?>

<html>
<head>
<link rel="stylesheet" href="assets/login.css">
</head>
<body>

<table class="center">
  <tr>
    <td colspan=2 align="center">
      <img src="assets/<? echo $config['siteLogo']; ?>"><br><br>
    </td>
  </tr>
  <tr>
    <td>
<div class='acctinfo'>
  <div class='titlebar'>
<? if ($id && $isAdmin) { ?>
    <? echo $id; ?> Play Log
<? } else { ?>
    Play Log
<? } ?>    
  </div>
  
<? if ($complaint) {

  echo "<p><font color='red'><b>Error:</b></font></b> $complaint";

    } else if ($message) {

  echo "<p><i>$message</i>";
  
    } ?>
<? if (sizeof($play_log)>1) { ?>
    <p><table class="info">
      <tr>
        <th>Date</th>
        <th>Duration</th>
      </tr>
<? for (($i = sizeof($play_log) - 2) ; $i >= 1 ; $i -= 2) {

    $thisDate = date("M/d/Y",$play_log[$i + 0]);
    
    if ($play_log[$i + 1] < 60) {
      $thisTime = $play_log[$i + 1] . " seconds";
    } else if ($play_log[$i+1] < 3600) {
      $thisTime = round($play_log[$i+1]/60) . " minutes";
    } else {
      $thisTime = round($play_log[$i+1]/3600) . " hour";
    }  
?>    
      <tr>
        <td align='center'><? echo $thisDate; ?></td>
        <td align='center'><? echo $thisTime; ?></td>
      </tr>
<? } ?>
    </table>
<? } ?>
<? if ($id && $isAdmin) { ?>
<p align="right"><i>return to <a href="support.php">support</a></i></p>
<? } else { ?>
<p align="right"><i>return to <a href="overview.php">overview</a></i></p>
<? } ?>
  </div>
    </td>
  </tr>
</table>
</body>
</html>
