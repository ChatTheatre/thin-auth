<?

#include("https.php");
include("userdb.php");

$config = read_config("general.json");

$siteName = $config['siteName'];
$cookieURL = set_cookie_url();
$suportEmail = $config['supportEmail'];

  $submit = $_POST["submit"];
  $uname = $_POST["uname"];
  if (!$uname) {
    $uname = $_GET['uname'];
  }
  $pwd = $_POST["pwd"];
  $phrase = $_POST["phrase"];

  $redirect = $_GET["redirect"];
  $timeout = $_GET["timeout"];
  $timeout_from = $_GET["timeout_from"];
  if (!$timeout_from) {
    $timeout_from = $_POST["timeout_from"];
  }
  $game = $_GET["game"];
  
# In userdb.php
  $biscuit = $_COOKIE["biscuit"];
  $return = $_COOKIE["return"];
  $user = $_COOKIE["user"];
  $pass = $_COOKIE["pass"];

  $complaint = $_COOKIE["complaint"];

if ($redirect) {
   setcookie("return", "login/" . $redirect, 0, "/", $cookieURL);
   $return = $redirect;
}

if (!$biscuit) {
   setcookie("biscuit", "test", 0, "/", $cookieURL);
}

if ($timeout) {
   $complaint = "Your previous login has timed out. Please login again.";
} else if ($submit) {
   if ($uname == "") {
      $complaint = "You must enter a user name.";
   } else if ($pwd == "") {
      $complaint = "You must enter a password.";
   } else if (!$biscuit) {
      $complaint = "You are not accepting cookies from $siteName, which means that we are unable to log you in. Please visit your security settings and allow cookies from $siteName, then return here and reload this page.";
   } else {
      if (passLogin($auth_sock,
		    strtolower($uname), $pwd,
		    $user, $pass,
		    $complaint)) {
	 if ($timeout_from) {
	     Header("Location: " . urldecode($timeout_from));
	     exit;
	 }

	 if (checkNextStep($auth_sock, $user, $pass, $return, $complaint)) {
	    /* passLogin succeeded; checkNextStep redirected: exit */

	    exit;
	 }
	 /* passLogin succeeded; checkNextStep reported a $complaint */
      }
      /* or passLogin reported a $complaint */
   }
}

  $pageName = "Account Login";
  $noBorder = 1;

#  include("Skotos/games.php");
#  include("Skotos/admin-header.php");

#  $gameInfo = newGames();	
?>
<?php //              onload="document.f.uname.focus(); ?>
<html>
<head>
<script type="text/javascript">
        if (top !== self) top.location.href = self.location.href;
</script>
<link rel="stylesheet" href="assets/login.css">
</head>
<body>
<table class="center">
  <tr>
    <td align="center">
<div class="acctinfo">
      <table border="0" cellpadding="4" cellspacing="4">
      <FORM action="login.php" METHOD="POST" name="f" ENCTYPE="application/x-www-form-urlencoded">
        <INPUT type='hidden' NAME='submit', VALUE='true'>
        <INPUT TYPE='hidden' NAME='phrase' VALUE='<? echo $phrase; ?>'>
  <tr>
    <td colspan=2 align="center">
      <img src="assets/<? echo $config['siteLogo']; ?>">
    </td>
  </tr>

<? if ($complaint) {

  echo "<tr><td colspan=2>";
  echo "<b><font color='red'>Error:</font></b> $complaint<br><br>";
  echo "</td></tr>";

  }

  if ($timeout_from) { ?>

        <INPUT type=hidden NAME="timeout_from" VALUE="<? print(htmlspecialchars($timeout_from)); ?>">

<?  } ?>
	  <TR VALIGN="MIDDLE">
	    <TD ALIGN="RIGHT" nowrap><b>User name:</b></TD>
	    <TD ALIGN="LEFT">
	      <? print ("<INPUT TYPE='TEXT' NAME='uname' VALUE='$uname' SIZE='20'>\n") ?>
	    </TD>
	  </TR>
	  <TR VALIGN="MIDDLE">
	    <TD ALIGN="RIGHT"><b>Password:</b></TD>
	    <TD ALIGN="LEFT">
	      <? print ("<INPUT TYPE='PASSWORD' NAME='pwd' VALUE='$pwd' SIZE='20'>\n") ?>
	    </TD>
	  </TR>
	  <TR VALIGN="MIDDLE">
	    <TD>&nbsp;</TD>
	    <TD><INPUT TYPE="SUBMIT" VALUE="Log in!"></input></TD>
	  </TR>
</form>
        </table>
</div>
<br><br>
<div class="acctinfo subinfo">
	    <p><b>First Visit?</b><br>
            <A HREF="create-account.php">Create</a> a <? echo $siteName; ?> account.
            <p><b>Forgot your password?</b><br>
            You Can <A HREF="lost-passwd.php">Recover</A> it.
</div>
</td></tr></table>
<script language="JavaScript"><!--
document.f.uname.focus();
//--></script>
<?php # include("Skotos/admin-footer.php"); ?>
</body>
</html>
