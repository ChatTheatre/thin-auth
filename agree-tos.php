<?

include("userdb.php");

$config = read_config("general.json");

$siteName = $config['siteName'];

  $submit = $_POST["submit"];
  $agree = $_POST["agree"];

$redirect = $_GET["redirect"];
  $timeout = $_GET["timeout"];
  $timeout_from = $_GET["timeout_from"];
  if (!$timeout_from) {
    $timeout_from = $_POST["timeout_from"];
  }
 
# In userdb.php
  $biscuit = $_COOKIE["biscuit"];
  $return = $_COOKIE["return"];
  $user = $_COOKIE["user"];
  $pass = $_COOKIE["pass"];

  $complaint = $_COOKIE["complaint"];

if ($user == "" || checkTimeOut($auth_sock, $user, $pass, $complaint)) {
     Header("Location: login.php?timeout=1&timeout_from=" .
       urlencode($_SERVER['PHP_SELF'] . ($_SERVER['QUERY_STRING'] ? "?" . $_SERVER['QUERY_STRING'] : "")));
      exit;
}

if (GetAccountStatus($ctl_sock,$user,"terms-of-service",$complaint)) {
  Header("Location: overview.php");
}


if ($submit) {
  if (!$agree) {
    echo "<p><font color='red'><b>Error:</b> You must agree to the TOS.</font></p>";
  } else if ($agree) {
    setAccountStatus($ctl_sock,$user,"terms-of-service",$complaint);
    Header("Location: overview.php");
  }
}
?>
<?php ?>
<html>
<head>
<link rel="stylesheet" href="assets/login.css">
</head>
<body>
<table width="100%" border="0" cellspacing="0" cellpadding="8" align="middle" height="100%">
  <tr height="100%">
<h1>Agree to <? echo $siteName; ?> Terms</h1>

<p>You must agree to the <? echo $siteName; ?> Terms of Service!</p>

<hr>
<? include "assets/tos.txt"; ?>
<hr>

<form action="<? echo $_SERVER['PHP_SELF']; ?>" method="post">
<input type="hidden" name="submit" value="1">
      <input type="checkbox" id="agree" name="agree" value="1">I have read and agree to the <a href="tos.php" target="_blank">Terms of Service</a>.
      <input type="submit">
      </form>

</body>
</html>
