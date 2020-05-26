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

# What months do we have reports on?

  report_oldest($ctl_sock,$oldestPlay,$complaint);
  $oldMonth = date("m",$oldestPlay);
  $oldYear = date("Y",$oldestPlay);
  
  $currentTime = mktime();
  $curMonth = date("m",$currentTime);
  $curYear = date("Y",$currentTime);

  $logDate = $_POST['logDate'];
  if (!$logDate) {
    $logDate = "$curMonth/$curYear";
  }
  if ($logDate == "$curMonth/$curYear") {
    $incomplete = " partial";
  } else {
    $incomplete = "";
  }

# Create month selector

  $dateSelect = "<select name='logDate' onchange='this.form.submit()'>";
  while (1) {

    if ($logDate == "$curMonth/$curYear") {
      $dateSelect .= "<option value='$curMonth/$curYear' SELECTED>$curMonth/$curYear";
    } else {
      $dateSelect .= "<option value='$curMonth/$curYear'>$curMonth/$curYear";
    }
    
    $curMonth--;

    if ($curYear == $oldYear && $curMonth < $oldMonth) {
      break;
    }
    
    if ($curMonth <= 0) {
      $curMonth = 12;
      $curYear--;
    }
  }
  $dateSelect .= "</select>";

# Gather other information

  report_other($ctl_sock,"pay",$logDate,$report_pay,$complaint);
  report_other($ctl_sock,"play",$logDate,$report_play,$complaint);
  
?>

<html>
<head>
<link rel="stylesheet" href="assets/login.css">
</head>
<body>

<table class="center">
<form action="<? echo $_SERVER['PHP_SELF']; ?>" method="post">
  <tr>
    <td colspan=2 align="center">
      <img src="assets/<? echo $config['siteLogo']; ?>"><br><br>
    </td>
  </tr>
  <tr>
    <td>
<div class='acctinfo doublewide'>
  <div class='titlebar'>
    Reporting Log <? echo $dateSelect; ?>
  </div>
  
<? if ($complaint) {

  echo "<p><font color='red'><b>Error:</b></font></b> $complaint";

    } else if ($message) {

  echo "<p><i>$message</i>";
  
    } ?>
<? if ($report_pay) { ?>

<? $total_funds = $report_pay[3]+$report_pay[7] + report_pay[11]; ?>

<p><h2>Pay Report (<? echo $logDate . $incomplete; ?>)</h2>
<p><b>Basic Accounts:</b> <? echo $report_pay[2]; ?> months purchased (<? echo $report_pay[1]; ?> events), $<? echo $report_pay[3]; ?>
<br><b>Premium Accounts:</b> <? echo $report_pay[6]; ?> months purchased (<? echo $report_pay[5]; ?> events), $<? echo $report_pay[7]; ?>
<br><b>Story Points:</b> <? echo $report_pay[10]; ?> SPs purchased (<? echo $report_pay[9]; ?> events), $<? echo $report_pay[11]; ?><br>
<p><b>Total Funds (<? echo $logDate; ?>):</b> $<? echo $total_funds; ?>
<? if ($fconfig['royalties']) {

echo "<p><b>Royalties:</b><blockquote>";

  foreach ($fconfig['royalties'] as $key => $value) {
    echo "<i>$key ($value%):</i> $" . number_format($total_funds*($value/100),2) . "<br>";
  }
  
} ?>
</blockquote>
<hr>
<p><h2>Play Report (<? echo $logDate . $incomplete; ?>)</h2>
<p><b>Plays:</b> <? echo $report_play[1]; ?> (<? echo $report_play[3]; ?> users)
<?

   $secondsPlay = $report_play[5];
   $hoursPlay = round($secondsPlay/3600);
   $avgPlay = round($hoursPlay / $report_play[3]);

?>
<br><b>Total Time:</b> <? echo $hoursPlay; ?> hours (<? echo $avgPlay; ?> hours per user)
<? } ?>
  </div>
  <div class="acctinfo doublewide" align='center'>
      <i>return to: <a href="support.php">support page</a> •
      <a href="overview.php">your overview</a></i>
  </div>
    </td>
  </tr>
</form>
</table>
</body>
</html>
