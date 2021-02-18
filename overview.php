<?

require_once("userdb.php");
$config = read_config("general.json");
$siteName = $config['siteName'];

if ($config['webURL']) {
    $webURL = strpos($config['webURL'], 'http') !== False ? $config['webURL'] : ('http://' . $config['webURL']);
}
if ($config['woeURL']) {
    $woeURL = strpos($config['woeURL'], 'http') !== False ? $config['woeURL'] : ('http://' . $config['woeURL']);
}

$gameURL = strpos($config['gameURL'], 'http') !== False ? $config['gameURL'] : ('http://' . $config['gameURL']);

$paypalCF = read_config("financial.json");

# Not logged in? GOTO Login page.

# In userdb.php

  $user = $_COOKIE["user"];
  $pass = $_COOKIE["pass"];

  $success = $_GET['success'];

  if ($user == "" || checkTimeOut($auth_sock, $user, $pass, $complaint)) {
     Header("Location: login.php?timeout=1&timeout_from=" .
	    urlencode($_SERVER['PHP_SELF'] . ($_SERVER['QUERY_STRING'] ? "?" . $_SERVER['QUERY_STRING'] : "")));
     exit;
  }
  $isAdmin = isAdmin($auth_sock,$user,$pass,$complaint);
  
  $complaint = "";

# RECORDS LOTS MORE INFO

  $email      = getNewProperty($auth_sock, $user, $pass, $complaint, "email");
  $otype       = getNewProperty($auth_sock, $user, $pass, $complaint, "account_type");
  $type = $otype;
  
  if ($type == "regular") {
    $daysLeft = getNewProperty($auth_sock, $user, $pass, $complaint, "paiddays");
  } else if ($type == "trial") {
    $daysLeft = getNewProperty($auth_sock, $user, $pass, $complaint, "trialdays");
  }
  if (($type == "regular" || $type == "trial") && !$daysLeft) {
    $daysLeft = "expired";
  }

  $premium      = getNewProperty($auth_sock, $user, $pass, $complaint, "premium");

  if ($type == "regular" && $premium) {
    $type = "premium";
  }

  $storypoints = getNewProperty($auth_sock,$user,$pass,$complaint,"storypoints:available");
  
# COMPLAINTS AND ALERTS

  if (!$complaint) {
     $complaint = $comp2;
  }

?>
<html>
<head>
<link rel="stylesheet" href="assets/login.css">
</head>
<body>

<!-- Outer Table -->

<table class="center">
  <tr>
    <td colspan=2 align="center">
      <img src="assets/<? echo $config['siteLogo']; ?>">
    </td>
  </tr>

<!-- Outer Table Row 2: Complaints & Messages -->

<? if ($complaint) {

  echo "<tr><td colspan=2>";
  echo "<b><font color='red'>Error:</font></b> $complaint<br><br>";
  echo "</td></tr>";
  
    } else if ($success) { 

  echo "<tr><td>";
  echo "<b><font color='green'>Success:</font></b> Your $success was changed.<br><br>";
  echo "</td></tr>";
  
    } ?>

  <tr>
    <td>

<!-- Inner Table: Left Column -->

      <div class="acctinfo">
      <div class="titlebar">
        Account Info
      </div>	
      <p><b>Account:</b> <? echo $user; ?><br>
      <b>Email:</b> <? echo $email; ?><br>
      <b>Acct. Type:</b> <? echo $type; ?><br>
<? if ($daysLeft) { ?>
      <b>Days Left:</b> <? echo $daysLeft; ?>
<? if ($type != "trial") { ?>
<span style='float: right'>[ <i><a href="billing.php">see bill log</a></i> ]</span>
<? } ?> 
      <br>
<? } ?>
<? if ($storypoints) { ?>      
      <b>StoryPoints:</b> <? echo $storypoints; ?>
<span style='float: right'>[ <i><a href="storypoints.php">see sp log</a></i> ]</span>
<br>
<? } ?>      
      </div>
      <div class="acctinfo">
        <table width='100%'>
          <tr>
	    <td colspan=2>
              <div class="titlebar">Main Options</div>
	    </td>
	  </tr>
	  <tr>
	    <td width='50%'>
	      <ul>
	      <br>
              <li><a href="<? echo $gameURL; ?>">Play Game</a>
<? if ($webURL) { ?>
              <li><a href="<? echo $webURL; ?>">Visit Web Pages</a>
<? } ?>	      
              <li><a href="logout.php">Logout Account</a>
	      </ul>
	    </td>
	    <td width='50%'>
	      <p><ul>
	      <br>
              <li><a href="change-passwd.php">Change Pass</a>
              <li><a href="change-email.php">Change Email</a>
	      </ul>
	    </td>
	  </tr>
<? if ($isAdmin) { ?>
	  <tr>
	    <td colspan=2>
              <div class="titlebar subbar">Admin Options</div>
	    </td>
	  <tr>
	  <tr>
	    <td width='50%'>
              <ul>
	        <br>
                <li><a href="support.php">Go to Support</a>
	      </ul>
	    </td>
	    <? if ($config['woeURL']) { ?>
	    <td width='50%'>
	      <ul>
	        <br>
                <li><a href="<? echo $woeURL; ?>">Go to Woe</a>
              </ul>
	    </td>
	    <? } ?>
	  </tr>	  
<? } ?>
	  <tr>
	    <td colspan=2>
              <div class="titlebar subbar">View Docs</div>
	    </td>
	  <tr>
	  <tr>
	    <td width='50%'>
              <ul>
	        <br>
                <li><a href="view-tos.php">View TOS</a>
	      </ul>
	    </td>
	    <td width='50%'>
	      <ul>
	        <br>
                <li><a href="view-privacy.php">View Privacy</a>
              </ul>
	    </td>
	  </tr>
<? if (!$isAdmin) { ?>	  
	  <tr>
	    <td colspan=2>
              <div class="titlebar subbar">Leave Game</div>
	    </td>
	  </tr>
	  <tr>
	    <td colspan=2>
	      <ul>
	        <br>
                <li><a href="delete-account.php">Delete Account</a>
              </ul>
            </td>
          </tr>
<? } ?>	  
        </table>
      </div>
    </td>
    <td>

<?

  $pointPurchase = $_POST['pointPurchase'];
  if ($otype != "staff" && $otype != "developer" && $otype != "free" && !$pointPurchase) { ?>

<!-- Inner Table: Right Column -->
<?

if (($type == "trial" || $type == "regular" || $daysLeft == "expired") &&
       ($paypalCF['basicMonth'] ||
        $paypalCF['basicQuarter'] ||
        $paypalCF['basicYear'])) {

?>
<div class="acctinfo">
<? if ($daysLeft == "expired") { ?>
  <div class="titlebar alert">
    Basic Subscriptions
  </div>
<? } else { ?>
  <div class="titlebar">
    Basic Subscriptions
  </div>
<? } ?>
<!-- Basic: One Month -->

<?

if ($daysLeft == "expired") { 
  echo "<p><i>Please support $siteName.</i></p>";
} else if ($daysLeft < 4) {
  echo "<p><i>It's renewal time!</i></b>";
}

?>

<? if ($paypalCF['basicMonth']) { ?>

<p>
<form class="paypal" action="https://www.paypal.com/cgi-bin/webscr" method="post">
<span style="vertical-align: middle"><b>Basic, 1 mo. ($<? echo $paypalCF['basicMonth']; ?>):</b></span>
<input type="hidden" name="cmd" value="_xclick">
<input type="hidden" name="business" value="<? echo $paypalCF['paypalAcct']; ?>">
<input type="hidden" name="custom" value="<? echo $user; ?>">
<input type="hidden" name="item_name" value="One Month Basic">
<input type="hidden" name="amount" value="<? echo $paypalCF['basicMonth']; ?>">
<input type="hidden" name="no_note" value="1">
<input type="hidden" name="currency_code" value="USD">
<input type="hidden" name="notify_url" value="https://<? echo $config['userdbURL']; ?>/subscribe-paypal-verify.php">
<input style="vertical-align: middle" type="image" src="https://www.paypal.com/en_US/i/btn/x-click-but23.gif" border="0" name="submit" alt="Make payments with PayPal - it's fast, free and secure!">
</form>

<? } ?>

<!-- Basic: One Quarter -->

<? if ($paypalCF['basicQuarter']) { ?>

<p><form class="paypal" action="https://www.paypal.com/cgi-bin/webscr" method="post">
<span style="vertical-align: middle"><b>Basic, 3 mos. ($<? echo $paypalCF['basicQuarter']; ?>):</b></span>
<input type="hidden" name="cmd" value="_xclick">
<input type="hidden" name="business" value="<? echo $paypalCF['paypalAcct']; ?>">
<input type="hidden" name="custom" value="<? echo $user; ?>">
<input type="hidden" name="item_name" value="One Quarter Basic">
<input type="hidden" name="amount" value="<? echo $paypalCF['basicQuarter']; ?>">
<input type="hidden" name="no_note" value="1">
<input type="hidden" name="currency_code" value="USD">
<input type="hidden" name="notify_url" value="https://<? echo $config['userdbURL']; ?>/subscribe-paypal-verify.php">
<input style="vertical-align: middle" type="image" src="https://www.paypal.com/en_US/i/btn/x-click-but23.gif" border="0" name="submit" alt="Make payments with PayPal - it's fast, free and secure!">
</form>

<? } ?>

<!-- Basic: One Year -->

<? if ($paypalCF['basicYear']) { ?>

<p><form class="paypal" action="https://www.paypal.com/cgi-bin/webscr" method="post">
<span style="vertical-align: middle"><b>Basic, 1 year ($<? echo $paypalCF['basicYear']; ?>):</b></span>
<input type="hidden" name="cmd" value="_xclick">
<input type="hidden" name="business" value="<? echo $paypalCF['paypalAcct']; ?>">
<input type="hidden" name="custom" value="<? echo $user; ?>">
<input type="hidden" name="item_name" value="One Year Basic">
<input type="hidden" name="amount" value="<? echo $paypalCF['basicYear']; ?>">
<input type="hidden" name="no_note" value="1">
<input type="hidden" name="currency_code" value="USD">
<input type="hidden" name="notify_url" value="https://<? echo $config['userdbURL']; ?>/subscribe-paypal-verify.php">
<input style="vertical-align: middle" type="image" src="https://www.paypal.com/en_US/i/btn/x-click-but23.gif" border="0" name="submit" alt="Make payments with PayPal - it's fast, free and secure!">
</form>

<?

  }

if ($type == "regular" && $daysLeft != "expired") { ?>

<ul><li><b><i><a href="convert-to-premium.php">Convert account to premium</a></i></b></ul>

<? }

  echo "</div>";
}


if (($type == "trial" || $type == "premium" || $daysLeft == "expired") &&
       ($paypalCF['premiumMonth'] ||
        $paypalCF['premiumQuarter'] ||
        $paypalCF['premiumYear'])) {

?>
<div class="acctinfo">
<? if ($daysLeft == "expired") { ?>
  <div class="titlebar alert">
    Premium Subscriptions
  </div>
<? } else { ?>
  <div class="titlebar">
    Premium Subscriptions
  </div>
<? } ?>

<?

if ($daysLeft < 4 && $daysLeft != "expired") {
  echo "<p><i>It's renewal time!</i></b>";
}

?>

<!-- Premium: One Month -->

<? if ($paypalCF['premiumMonth']) { ?>

<p>
<form class="paypal" action="https://www.paypal.com/cgi-bin/webscr" method="post">
<span style="vertical-align: middle"><b>Premium, 1 mo. ($<? echo $paypalCF['premiumMonth']; ?>):</b></span>
<input type="hidden" name="cmd" value="_xclick">
<input type="hidden" name="business" value="<? echo $paypalCF['paypalAcct']; ?>">
<input type="hidden" name="custom" value="<? echo $user; ?>">
<input type="hidden" name="item_name" value="One Month Premium">
<input type="hidden" name="amount" value="<? echo $paypalCF['premiumMonth']; ?>">
<input type="hidden" name="no_note" value="1">
<input type="hidden" name="currency_code" value="USD">
<input type="hidden" name="notify_url" value="https://<? echo $config['userdbURL']; ?>/subscribe-paypal-verify.php">
<input style="vertical-align: middle" type="image" src="https://www.paypal.com/en_US/i/btn/x-click-but23.gif" border="0" name="submit" alt="Make payments with PayPal - it's fast, free and secure!">
</form>

<? } ?>

<!-- Premium: One Quarter -->

<? if ($paypalCF['premiumQuarter']) { ?>

<p><form class="paypal" action="https://www.paypal.com/cgi-bin/webscr" method="post">
<span style="vertical-align: middle"><b>Premium, 3 mos. ($<? echo $paypalCF['premiumQuarter']; ?>):</b></span>
<input type="hidden" name="cmd" value="_xclick">
<input type="hidden" name="business" value="<? echo $paypalCF['paypalAcct']; ?>">
<input type="hidden" name="custom" value="<? echo $user; ?>">
<input type="hidden" name="item_name" value="One Quarter Premium">
<input type="hidden" name="amount" value="<? echo $paypalCF['premiumQuarter']; ?>">
<input type="hidden" name="no_note" value="1">
<input type="hidden" name="currency_code" value="USD">
<input type="hidden" name="notify_url" value="https://<? echo $config['userdbURL']; ?>/subscribe-paypal-verify.php">
<input style="vertical-align: middle" type="image" src="https://www.paypal.com/en_US/i/btn/x-click-but23.gif" border="0" name="submit" alt="Make payments with PayPal - it's fast, free and secure!">
</form>

<? } ?>

<!-- Premium: One Year -->

<? if ($paypalCF['premiumYear']) { ?>

<p><form class="paypal" action="https://www.paypal.com/cgi-bin/webscr" method="post">
<span style="vertical-align: middle"><b>Premium, 1 year ($<? echo $paypalCF['premiumYear']; ?>):</b></span>
<input type="hidden" name="cmd" value="_xclick">
<input type="hidden" name="business" value="<? echo $paypalCF['paypalAcct']; ?>">
<input type="hidden" name="custom" value="<? echo $user; ?>">
<input type="hidden" name="item_name" value="One Year Premium">
<input type="hidden" name="amount" value="<? echo $paypalCF['premiumYear']; ?>">
<input type="hidden" name="no_note" value="1">
<input type="hidden" name="currency_code" value="USD">
<input type="hidden" name="notify_url" value="https://<? echo $config['userdbURL']; ?>/subscribe-paypal-verify.php">
<input style="vertical-align: middle" type="image" src="https://www.paypal.com/en_US/i/btn/x-click-but23.gif" border="0" name="submit" alt="Make payments with PayPal - it's fast, free and secure!">
</form>
<?
  }
  
if ($type == "premium" && $daysLeft != "expired") { ?>

<ul><li><b><i><a href="convert-to-basic.php">Convert account to basic</a></i></b></ul>

<? }

  echo "</div>";
}
}



?>

<?

# SP Box


if ($type != "trial" && $daysLeft != "expired" && ( $paypalCF['spCostBasic'] || $paypalCF['spCostPremium'] ) ) {


  $spUsed = storypoints_purchased($ctl_sock,$user,0,$complaint);
  $spMax = ($premium ? $paypalCF['spMaxPremium'] : $paypalCF['spMaxBasic']);
  $spLeft = $spMax - $spUsed;
  $spCost = ($premium ? $paypalCF['spCostPremium'] : $paypalCF['spCostBasic']);

?>
<div class="acctinfo">
  <div class="titlebar">
    StoryPoint Purchase
  </div>
<?
  if ($spLeft < 50) {

# Can't Buy

    echo "<p>You are limited to <b>$spMax</b> StoryPoints per month for a <b>$type</b> account. You have already bought <b>$spUsed</b> in the last 30 days. ";

    $spUsedShorter = storypoints_purchased($ctl_sock,$user,27,$complaint);
    $spLeftShorter = $spMax - $spUsedShorter;

    if ($spLeftShorter >= 50) {
      echo "<p><i>You'll be able to buy points again in a few days!</i> ";
    }
    if ($type != "premium") {
      echo "If you <a href='convert-to-premium.php'><b>upgrade to a premium account</b></a>, you will be able to buy <b>" . $paypalCF['spMaxPremium'] . "</b> StoryPoints each month.";
    }
  } else {

# Can Buy

    $spUsedLonger = storypoints_purchased($ctl_sock,$user,37,$complaint);
    $spLeftLonger = $spMax - $spUsedLonger;

    echo "<p><center><table>";
    echo "<form action=\"$url\" method=\"post\">";

    if ($return) {
      echo "<input type=\"hidden\" name=\"return\" value=\"$return\">";
    }

# 1. Intro

    if ($spLeftLonger < 50) {
      echo "<p><b><i>You can buy StoryPoints again!</I></b>";
    }

# 2. Points
  
    echo "<tr><td><b>Purchase:</b></td><td>";
    echo "<select name=\"pointPurchase\" onchange='this.form.submit()'>";

    echo "<option SELECTED value='0'>No SPs";

    for ($i = 50 ; $i <= $spLeft ; $i+=50) {
    
      if ($i == $pointPurchase) {
        echo "<option SELECTED value=\"$i\">$i SPs - \$" . number_format(($i/50)*$spCost,2);
      } else {
        echo "<option value=\"$i\">$i SPs - \$" . number_format(($i/50)*$spCost,2);
      }
    }
    
    echo "</select>";

# 3. PayPal Form

    echo "</form>";  
    echo "</td></tr>";

    if ($pointPurchase) {
    
      echo "<tr><td colspan=2 align='right'><br>";

      if ($pointPurchase > $spLeft) {
      
        echo "<p><b>Error:</b> It looks like you are trying to buy too many points!";

      } else {
      
        $thisCost = ($pointPurchase / 50) * $spCost;
	
?>
<form action="https://www.paypal.com/cgi-bin/webscr" method="post">
<input type="hidden" name="cmd" value="_xclick">
<input type="hidden" name="business" value="<? echo $paypalCF['paypalAcct']; ?>">
<input type="hidden" name="custom" value="<? echo $user; ?>::<? echo $type; ?>::<? echo $pointPurchase; ?>">
<input type="hidden" name="item_name" value="<? echo $pointPurchase;?> SPs from <? echo $siteName; ?>">
<input type="hidden" name="amount" value="<? echo $thisCost; ?>">
<input type="hidden" name="no_shipping" value="0">
<input type="hidden" name="no_note" value="1">
<input type="hidden" name="currency_code" value="USD">
<input type="hidden" name="notify_url" value="https://<? echo $config['userdbURL']; ?>/storypoints-paypal-verify.php">
<input type="hidden" name="lc" value=US">
<input type="hidden" name="bn" value="PP-BuyNowBF">
<input type="image" src="https://www.paypal.com/en_US/i/btn/btn_paynow_SM.gif" border="0" name="submit" alt="Make payments with PayPal - it's fast, free and secure!">
<img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1">
</form>

<?  
      }
      echo "</td></tr>";
    }

    echo "</table></center>";

    if (!$pointPurchase && $spLeft >= 50) {  

      echo "<blockquote><i>As a <b>$type</b> member, you may buy up to <b>" . $spMax . "</b> StoryPoints per month using <b>Paypal</b>. ";
 
      if ($spUsed) {
        echo "You have previously bought <b><i>$spUsed</I></b> StoryPoints this month.";
      }
    }

    if ($pointPurchase) {

      echo "<blockquote><i>Selecting this 'Pay Now' button will purchase  <b>$pointPurchase SPs</b> for <b>$" . number_format($thisCost,2) . "</b>. We will email you when Paypal confirms your transaction.";

      if ($config['gameID'] == "tec") {

        $ratio = ($paypalCF['RPRatio'] ? $paypalCF['RPRatio'] : 4);

        echo "<p>This purchase will equal <b>" . $ratio * $pointPurchase . " Rolepoints</b>. Type @play once you're in TEC and select 'Convert Story Points'.";
       }
     }

    echo "</blockquote>";
  }
  echo "</div>";
}  

?>

    </td>
  </tr>
</table>  
</body>
</html>

