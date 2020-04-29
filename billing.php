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

  $complaint = "";

  $bill_log = getNewProperty($auth_sock,$user,$pass,$complaint,"billinglog");
  if (!$bill_log && !$complaint) {
    $message = "You have not yet paid for $siteName.";
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
<div class='acctinfo doublewide'>
  <div class='titlebar'>
    Billing Log
  </div>
  
<? if ($complaint) {

  echo "<p><font color='red'><b>Error:</b></font></b> $complaint";

    } else if ($message) {

  echo "<p><i>$message</i>";
  
    } ?>
<? if ($bill_log) {

  $exploded_log = explode(PHP_EOL,$bill_log);

?>

    <p><table class="info">
      <tr>
        <th>Date</th>
	<th>Event</th>
      </tr>
<? for ($i = 0 ; $i < sizeof($exploded_log) ; $i++) {

     $thisLine = preg_split('#\s+#',$exploded_log[$i],2);
?>
      <tr>
        <td><? echo $thisLine[0]; ?></td>
        <td><? echo $thisLine[1]; ?></td>	
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
