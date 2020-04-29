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

  $type       = getNewProperty($auth_sock, $user, $pass, $complaint, "account_type");
  $premium       = getNewProperty($auth_sock, $user, $pass, $complaint, "premium");
  
  if ($premium || $type != "regular") {
    Header("Location: overview.php");
    exit;
  }
  
  $daysLeft = getNewProperty($auth_sock, $user, $pass, $complaint, "paiddays");
  $paypalCF = read_config("financial.json");
  $convertedDays = floor($daysLeft / $paypalCF['premiumToBasicConversion']);
    
  
?>
<?
$submit = $_POST['submit'];

if ($submit) {

  $convert = $_POST['convert'];

  if (!$convert) {
    $complaint = "You must select the checkbox";

  } else {

    convertAccount($auth_sock,$user,$pass,"premium",$complaint);
    if (!$complaint) {
    
      Header("Location: overview.php");
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
    <td>

      <div class="acctinfo">
        <div class="titlebar">
    <b>Convert to Premium</b>
        </div>

<? if ($complaint) {

  echo "<p><b><font color='red'>Error:</font></b> $complaint</p>";
  
    } else {

   echo "<p>If you convert your account to premium, your $daysLeft basic days will become " . ($convertedDays - 1) . " to $convertedDays premium days.</p>";

   }
?>

<form action="<? echo $_SERVER['PHP_SELF']; ?>" method="post">
<input type="hidden" name="submit" value="1">
<table>
  <tr>
    <td>
      <input type="checkbox" id="convert" name="convert" value="1">&nbsp;Yes, convert my account.
    </td>
  </tr>
  <tr>
    <td colspan=2 align="right">
      <br><input type="button" name="cancel" value="Cancel" onClick="window.location='overview.php'">
      <input type="submit">
    </td>
  </tr>
</table>
</form>

          </TD></TR>
</table>

</body>
</html>
