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

  $id = $_GET['id'];
  $isAdmin = isAdmin($auth_sock,$user,$pass,$complaint);

  if ($id && $isAdmin) {
    $thisUser = urldecode($id);
  } else {
    $thisUser = $user;
  }

  $bill_log = getProperty($ctl_sock,$thisUser,$complaint,"billinglog");
  if (!$bill_log && !$complaint) {
    if ($id && $isAdmin) {
      $message = "$id has not yet paid for $siteName.";    
    } else {
      $message = "You have not yet paid for $siteName.";
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
<div class='acctinfo doublewide'>
  <div class='titlebar'>
<? if ($id && $isAdmin) { ?>
    <? echo $id; ?> Billing Log
<? } else { ?>
    Billing Log
<? } ?>    
  </div>
  
<? if ($complaint) {

  echo "<p><font color='red'><b>Error:</b></font></b> $complaint";

    } else if ($message) {

  echo "<p><i>$message</i>";
  
    } ?>
<? if ($bill_log) {

  $exploded_log = explode(",",$bill_log);

?>

    <p><table class="info">
      <tr>
        <th>Date</th>
	<th>Event</th>
      </tr>
<? for ($i = 0 ; $i < sizeof($exploded_log) ; $i+=2) {

#     $thisLine = preg_split('#\s+#',$exploded_log[$i],2);
?>
      <tr>
        <td><? echo $exploded_log[$i]; ?></td>
        <td><? echo $exploded_log[$i+1]; ?></td>	
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
