<?

require_once("userdb.php");
$config = read_config("general.json");
$siteName = $config['siteName'];

# Not logged in? GOTO Login page.

# In userdb.php

  $uname = $_COOKIE["user"];
  $pass = $_COOKIE["pass"];

  if ($uname == "" || checkTimeOut($auth_sock, $uname, $pass, $complaint)) {
     Header("Location: login.php?timeout=1&timeout_from=" .
	    urlencode($_SERVER['PHP_SELF'] . ($_SERVER['QUERY_STRING'] ? "?" . $_SERVER['QUERY_STRING'] : "")));
     exit;
  }

  $oldemail = getNewProperty($auth_sock,$uname,$pass,$complaint,"email");
  $complaint = "";

$cancel = $_GET['cancel'];

  if ($cancel) {

    deletePing($ctl_sock,$uname,0,$complaint);
    if (!$complaint) {
      Header("Location: overview.php");
      exit;
    }
  }

  if (!checkPing($ctl_sock,$uname,$complaint)) {

    if ($complaint == "USER HAS NO EMAIL") {

      $complaint = "You must validate an email address. Please check your email and/or your spam box for the verification message.<br> If you've lost it, you can request a new message by filling in this form.";

    } else if ($complaint == "USER HAS NEW EMAIL") {

      getPing($auth_sock,$uname,$pass,$pinginfo,$complaint);
      $code = $pinginfo['code'];
      
      $complaint = "You must validate your new email address. Please check your email and/or your spam box for the verification message.<br> If you've lost it, you can request a new message by filling in this form. If you prefer, you can alternatively <a href=\"" . $_SERVER['PHP_SELF'] . "?cancel=$code\">cancel your email-address update</a>.";

    }
  }

    
?>
<?

$submit = $_POST['submit'];

if ($submit) {

  $newemail1 = $_POST['newemail1'];
  $newemail2 = $_POST['newemail2'];  

  if ($newemail1 != $newemail2) {

    $complaint = "Emails do not match!";

  } else if (!$newemail1) {

    $complaint = "You must enter an email!";
    
  } else if ($newemail1 == $oldemail) {

    $complaint = "That's the same as your old email!";
    
  } else {

    pingUser($auth_sock,$uname,$newemail1,$pass,$complaint);
    if (!$complaint) {
      $message = "Please check your email to verify your new email address.";
    }
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
<div class="acctinfo">
  <div class="titlebar">
    <b>Change <? echo $siteName; ?> Email</b>
  </div>

<? if ($complaint) {

  echo "<p><b><font color='red'>Error:</font></b> $complaint<br><br>";

    } else if ($message) {

  echo "<p><i>$message</i>";

    } ?>

<p><form action="<? echo $_SERVER['PHP_SELF']; ?>" method="post">
<input type="hidden" name="submit" value="1">
<table>
<?
if (!$token) {
  $token = $_GET['token'];
}
if ($token) { ?>
  <input type="hidden" name="token" value="<? echo $token; ?>">
<? } else { ?>
  <tr>
    <td align="right">
      <b>Account:</b>
    </td>
    <td>
      <? echo $uname; ?>
    </td>
  </tr>
  <tr>
    <td align="right">
      <b>Current Email:</b>
    </td>
    <td>
      <? echo $oldemail; ?>
    </td>  
  </tr>
  <tr>
    <td align="right">
      <b>New Email:</b>
    </td>
    <td>
      <input type="text" name="newemail1" id="newemail1" size="24">
    </td>
  </tr>
<? } ?>  
  <tr>
    <td align="right">
      <b>New Email (Again):</b>
    </td>
    <td>
      <input type="text" name="newemail2" id="newemail2" size="24">    
    </td>
  </tr>
  <tr>
    <td colspan=2 align="right">
      <input type="submit">
    </td>
  </tr>
</table>
</form>
</div>
          </TD></TR>
</table>

</body>
</html>
