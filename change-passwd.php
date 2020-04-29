<?

require_once("userdb.php");
$config = read_config("general.json");
$siteName = $config['siteName'];

# Not logged in? GOTO Login page.

# In userdb.php

  $user = $_COOKIE["user"];
  $pass = $_COOKIE["pass"];

  if ($user == "" || checkTimeOut($auth_sock, $user, $pass, $complaint)) {
     Header("Location: login.php?timeout=1&timeout_from=" .
	    urlencode($_SERVER['PHP_SELF'] . ($_SERVER['QUERY_STRING'] ? "?" . $_SERVER['QUERY_STRING'] : "")));
     exit;
  }

  $complaint = "";

?>
<?
$submit = $_POST['submit'];

if ($submit) {

  $oldpass = $_POST['oldpass'];
  $newpass1 = $_POST['newpass1'];
  $newpass2 = $_POST['newpass2'];
  $token = $_POST['token'];
  
  $uname = $_COOKIE["user"];
  $user = $pass = $complaint = "";
  if ($newpass1 != $newpass2) {

     $complaint = "Passwords do not match!";

  } else if ($token) {

    setPassword($ctl_sock,$uname,$token,$newpass1,$complaint,1);

    if (!$complaint) {
      Header("Location: overview.php?success=password");
      exit;
    }
    
  } else if (passLogin($auth_sock,
                strtolower($uname), $oldpass,
	        $user, $pass,
	        $complaint)) {

    setPassword($ctl_sock,$uname,$oldpass,$newpass1,$complaint);

    if (!$complaint) {
      Header("Location: overview.php?success=password");
      exit;
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
    <b>Change <? echo $siteName; ?> Password</b>
</div>    

<? if ($complaint) {

  echo "<p><b><font color='red'>Error:</font></b> $complaint<br><br>";
  
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
      <? echo $user; ?>
    </td>
  </tr>
  <tr>
    <td align="right">
      <b>Old Password:</b>
    </td>
    <td>
      <input type="password" name="oldpass" id="oldpass" size="16">
    </td>
  </tr>
<? } ?>  
  <tr>
    <td align="right">
      <b>New Password:</b>
    </td>
    <td>
      <input minlength="8" size="16" type="password" name="newpass1" id="newpass1">
    </td>
  </tr>
  <tr>
    <td align="right">
      <b>New Password:</b>
    </td>
    <td>
      <input minlength="8" size="16" type="password" name="newpass2" id="newpass2">
    </td>
  </tr>
  <tr>
    <td colspan=2 align="right">
      <input type="submit">
    </td>
  </tr>
<? if (!$token) { ?>  
  <tr>
    <td>
          </TD><TD ALIGN="RIGHT">
            <b>Forgot your password?</b><br>
            You Can <A HREF="lost-passwd.php">Recover</A> it.	    
          </TD></TR>
<? } ?>	  
</table>
</form>

</div>	  

          </TD></TR>
</table>

</body>
</html>
