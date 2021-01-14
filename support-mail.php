<?

require_once("userdb.php");
$config = read_config("general.json");
$siteName = $config['siteName'];
$fconfig = read_config("financial.json");

# In userdb.php

  $user = $_COOKIE["user"];
  $pass = $_COOKIE["pass"];

  if ($user == "" || checkTimeOut($auth_sock, $user, $pass, $complaint)) {
     Header("Location: login.php?email-code=$email_code&timeout=1&timeout_from=" .
	    urlencode($_SERVER['PHP_SELF'] . ($_SERVER['QUERY_STRING'] ? "?" . $_SERVER['QUERY_STRING'] : "")));
     exit;
  }

  $isAdmin = isAdmin($auth_sock,$user,$pass,$complaint);
  if (!$isAdmin) {
    Header("Location: overview.php");
  }

  $OK_user = $_GET['approve'];
  if ($OK_user) {
    deletePing($ctl_sock,$OK_user,TRUE,$complaint);
    if ($complaint) {
      echo "<p><b><font color='red'>ERROR:</font><b> Error with email confirmation, $complaint</p>";
    }
  }
  $pingList = listPing($ctl_sock);
  
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
    Pending Email Users
  </div>
<? if ($pingList) { ?>

   <table width="100%" cellpadding=2 cellspacing=0>
   <?
     $pingUsers = explode(" ",$pingList);
     for ($i = 0 ; $i < sizeof($pingUsers) ; $i++) {
       $thisUser = explode("::",$pingUsers[$i]);
       if ($i%2 == 0) {
         echo "<tr style=\"background-color:#dddddd\">";
       } else {
         echo "<tr style=\"background-color:#bbbbbb\">";
       }	 
         echo "<td align='center'>" . date('m/d/y',$thisUser[0]) . "</td>";
         echo "<td>$thisUser[2]</td>";
	 if ($thisUser[3] == $thisUser[4]) {
	   echo "<td><i>new user</I></td>";
	 } else {
	   echo "<td>$thisUser[3] -> $thisUser[4]</td>";
	 }
	 echo "<td align='center'>[ <a href=\"support-mail.php?approve=" . urlencode($thisUser[2]) . "\">approve</a> ]";
       echo "</tr>";
     }
   ?>
   </table>
<? } else { ?>
      <? echo "<p><i>There are no users awaiting confirmation.</i>"; ?>
<? } ?>

</div>
  <div class="acctinfo doublewide" align='center'>
      <i>return to: <a href="support.php">support page</a> •
      <a href="overview.php">your overview</a></i>
  </div>
    </td>
  </tr>    
</table>
</body>
</html>
