<?

require_once("userdb.php");
$config = read_config("general.json");
$siteName = $config['siteName'];
$cookieURL = set_cookie_url();

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

  $noemail = $_POST['noemail'];
  $delete = $_POST['delete'];

  if (!$delete) {
    $complaint = "You must agree to delete your account!";
  } else {

    setAccountStatus($ctl_sock,$user,"deleted",$complaint);    

    if ($noemail) {

     setAccountStatus($ctl_sock,$user,"no-email",$complaint);

    }

    setcookie("user", "",   time() - 3600 * 24, "/", $cookieURL);
    setcookie("pass", "",   time() - 3600 * 24, "/", $cookieURL);

    Header("Location: login.php");

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
    <b>Delete <? echo $siteName; ?> Account</b>
        </div>

<? if ($complaint) {

  echo "<p><b><font color='red'>Error:</font></b> $complaint</p>";
  
    } else {

      echo "<p>";

    }
    ?>

<form action="<? echo $_SERVER['PHP_SELF']; ?>" method="post">
<input type="hidden" name="submit" value="1">
<table>
  <tr>
    <td>
      <input type="checkbox" id="delete" name="delete" value="1">&nbsp;Yes, I really want to delete my account!
    </td>
  </tr>
  <tr>
    <td>
      <input type="checkbox" id="noemail" name="noemail" value="1">&nbsp;Remove me from your email list too!
    </td>
  </tr>  
  <tr>
    <td colspan=2 align="right">
    <br>
      <input type="button" name="cancel" value="Cancel" onClick="window.location='overview.php'">
      <input type="submit">
    </td>
  </tr>
</table>
</form>

          </TD></TR>
</table>

</body>
</html>
