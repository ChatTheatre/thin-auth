<?

require_once("userdb.php");
$config = read_config("general.json");
$siteName = $config['siteName'];

# In userdb.php

  $user = $_COOKIE["user"];
  $pass = $_COOKIE["pass"];
  $uid = getNewProperty($auth_sock, $user, $pass, $complaint, "ID");

  if ($user == "" || checkTimeOut($auth_sock, $user, $pass, $complaint)) {
     Header("Location: login.php?email-code=$email_code&timeout=1&timeout_from=" .
	    urlencode($_SERVER['PHP_SELF'] . ($_SERVER['QUERY_STRING'] ? "?" . $_SERVER['QUERY_STRING'] : "")));
     exit;
  }

  $complaint = "";
  storypoints_log($ctl_sock, $user, $sps_log, $complaint);

  if (!$sps_log && !$complaint) {
    $complaint = "You have no storypoints log!";
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
<div class='acctinfo doublewide'>
  <div class='titlebar'>
    Storypoints Log
  </div>
  
<? if ($complaint) {

  echo "<p><font color='red'><b>Error:</b></font></b> $complaint";

    } else if ($message) {

  echo "<p><i>$message</i>";
  
    } ?>
<? if ($sps_log) { ?>
    <p><table class="info">
      <tr>
        <th>Date</th>
        <th>SPs</th>
        <th>Reason</th>
        <th>Who</th>
        <th>Comment</th>	
      </tr>
<? for (($i = sizeof($sps_log) - 4) ; $i >= 0 ; $i -= 5) { ?>
      <tr>
        <td><? echo $sps_log[$i + 0]; ?></td>
        <td><? echo $sps_log[$i + 1]; ?></td>
        <td><? echo $sps_log[$i + 3]; ?></td>
        <td><? echo $sps_log[$i + 2]; ?></td>
        <td><? echo $sps_log[$i + 4]; ?></td>	
      </tr>
<? } ?>
    </table>
<? } ?>
<p align="right"><i>return to <a href="overview.php">overview</a></i></p>
  </div>
    </td>
  </tr>
</table>
</body>
</html>
