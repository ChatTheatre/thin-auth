<?php

# Default Variables

require_once("userdb.php");

$config = read_config("general.json");
$siteName = $config['siteName'];
$supportEmail = $config['supportEmail'];

$paypalCF = read_config("financial.json");
$paypalEmail = $paypalCF['paypalAcct'];

$email_sig = "\n\nThank you for your support of $siteName,\n\n$siteName Staff\n$supportEmail\n";

# Step 0: Read the Post from Paypal

# Step 0A: Add cmd

$req = 'cmd=_notify-validate';
foreach ($_POST as $key => $value) {

  $value = urlencode(stripslashes($value));
  $req .= "&$key=$value";

}

# Step 0B: Post it back

$header = "POST /cgi-bin/webscr HTTP/1.1\r\n";
$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
$header .= "Host: ipnpb.paypal.com\r\n";
$header .= "Connection: close\r\n";
$header .= "Content-Length: " . strlen($req) . "\r\n\r\n";
$fp = fsockopen ('tls://ipnpb.paypal.com', 443, $errno, $errstr, 30);
error_log("PAYPAL PAYMENT: $req",0);

# Step 0C: Assign posted variables to local variables

$user_name = $_POST['custom'];
$item_name = $_POST['item_name'];
$item_number = $_POST['item_number'];
$payment_status = $_POST['payment_status'];
$payment_amount = $_POST['mc_gross'];
$payment_currency = $_POST['mc_currency'];
$txn_id = $_POST['txn_id'];
$receiver_email = $_POST['receiver_email'];
$payer_email = $_POST['payer_email'];

if (preg_match("/one month/i",$item_name)) {
  $set_months = 1;
} else if (preg_match("/one quarter/i",$item_name)) {
  $set_months = 3;
} else if (preg_match("/one year/i",$item_name)) {
  $set_months = 12;
}

if (preg_match("/premium/i",$item_name)) {
  $set_account = "premium";
} else {
  $set_account = "regular";
}

if ($set_account == "regular") {
  if ($set_months == 1) {
    $acctCost = $paypalCF['basicMonth'];
  } else if ($set_months == 3) {
    $acctCost = $paypalCF['basicQuarter'];
  } else if ($set_months == 12) {
    $acctCost = $paypalCF['basicYear'];
  }
} else if ($set_account == "premium") {
  if ($set_months == 1) {
    $acctCost = $paypalCF['premiumMonth'];
  } else if ($set_months == 3) {
    $acctCost = $paypalCF['premiumQuarter'];
  } else if ($set_months == 12) {
    $acctCost = $paypalCF['premiumYear'];
  }
}  
  
$already_pending = getProperty($ctl_sock, $user_name, $complaint, "pendpaid");
$is_pending = ($payment_status == "Pending" ? TRUE : FALSE);

# Step 1: FAILURE: Could Not Open Paypal Socket

if (!$fp) {

  $paypalError = "ERROR: Could not contact Paypal Server ($user_name: $item_name / $payment_status) due to $errstr ($errno)\n\nThis payment should be verified & credited.\n";

  $paypalUserError = "We have been unable to successfully credit your payment due to an error at Paypal. $supportEmail has been notified, and we will credit your payment as soon as possible.\n";

# Step 1: SUCCESS: Opened Paypal Socket

} else {

  fputs ($fp, $header . $req);

  while (!feof($fp)) {
    $res = fgets ($fp, 1024);
    error_log("PAYPAL RESPONSE: $res",0);
    
# Step 2: FAILURE: Paypal IPN Invalid

    if (strcmp (substr($res,0,7), "INVALID") == 0) {

      $paypalError = "ERROR: Invalid Paypal IPN ($user_name: $item_name / $payment_status)\n";

      $banOK = banUser($ctl_sock, $user_name, "Bad IPN", "Paypal", $complaint);

      if ($banOK) {
        $paypalError .= "The account has been banned\n\n";
      } else {
        $paypalError .= "The UserDB failed to ban the account:\n$complaint\nYou should do it by hand.\n\n";
      }

# Step 2: SUCCESS: Paypal IPN Valid

    } else if (strcmp (substr($res,0,8), "VERIFIED") == 0) {

# Step 3: Switch on Status

      switch($payment_status) {

# Step 3A: Failed

        case "Failed":

 	  $paypalError = "ERROR: Possible Paypal Fraud ($user_name: $item_name / $payment_status)\nECHECK BOUNCED!\n\n";
          $banOK = banUser($ctl_sock, $user_name, "Payment FAILED", "Paypal", $complaint);
      
          if ($banOK) {
	    $paypalError .= "The account has been banned\n\n";
	  } else {
            $paypalError .= "The UserDB failed to ban the account:\n$complaint\nYou should do it by hand.\n\n";
	  }

	  $paypalUserError = "Your recent eCheck paid through Paypal has bounced and as a result your account has been closed. Please mail $supportEmail for information on how to reactivate your siteName account.";

          setProperty($ctl_sock, $user_name, $complaint, "pendpaid", 0);
	  break;

# Step 3B: Pending

	 case "Pending":

           $pending_reason == $_POST['pending_reason'];

# FAILURE: Already has a pending sale

	   if ($already_pending) {

             $paypalUserError = "WARNING: You have requested payment with a Paypal eCheck, though your last eCheck has not yet cleared. We can not credit your account with any additional time unless your original eCheck clears. If you think this warning is in error, please email $supportEmail";

	     $paypalError = "WARNING: Possible Paypal Fraud ($user_name: $item_name / $payment_status)\nMULTIPLE UNCLEARED ECHECKS.";

	     break;
	     
# FAILURE: Pending, but not echeck

           } else if ($pending_reason != "echeck") {

             $paypalError = "WARNING: PayPal Payment Marked Pending, But Not an Echeck ($user_name: $item_name / $pending_reason)\n";
	     break;

           }

# PARTIAL SUCESS: Drop Through to "Completed" if the Pending Failures Don't Occur

# Step 3C: Completed

         case "Completed":

# Step 3C-A: FAILURE: Fake Receiver Email
         
           if ($receiver_email != $paypalEmail) {

   	     $paypalError = "ERROR: Attempted Paypal Fraud ($user_name: $item_name / $payment_status)\nINVALID RECEIVER EMAIL ($receiver_email)\n";

	     $paypalError .= "\nAccount should probably be banned by hand.\n";

# Step 3C-B: FAILURE: duplicate transaction id

           } else if (!unusedPaypalTxnid($ctl_sock,$txn_id,$complaint)) {

  	     $paypalError = "ERROR: Attempted Paypal Fraud ($user_name: $item_name / $payment_status)\nREPETITIVE TRANSACTION ID ($txn_id): $complaint\n";
  	     $paypalError .= "\nAccount should probably be banned by hand.\n";

# Step 3C-C: FAILURE: wrong cost or wrong currency

           } else if ($payment_amount != $acctCost || $payment_currency != "USD") {
	   
   	     $paypalError = "ERROR: Attempted Paypal Fraud ($user_name: $item_name / $payment_status)\nINCORRECT MONETARY AMOUNT ($payment_amount $payment_currency should be $acctCost USD)\n";
	     $paypalError .= "\nAccount should probably be banned by hand.\n";

# Step 3D-D: SUCCESS: Payment Looks Legit

           } else {

             logPaypal($ctl_sock, $user_name, $item_name, $payment_amount, $payer_email, $txn_id, $complaint);

# SUCCESS: Verification of existing payment, just mark it off

             if ($already_pending) {
	      
               setProperty($ctl_sock, $user_name, $complaint, "pendpaid", 0);

   	       $paypalSuccess = "Your recent Paypal echeck has cleared, and your current Skotos payment ($acctCost for $set_account $set_months months) has been verified.\n";

# SUCCESS: New Payment! Log It!

             } else {

               billingOverview($ctl_sock,$user_name,$set_months,$set_account,$payment_amount,$is_pending,$complaint);

               if ($complaint) {
		 
 	         $paypalError = "ERROR: User Database Failure: $complaint ($user_name: $item_name / $payment_status\n\n$user_name should be credited by hand!\n";

	         $paypalUserError = "We have been unable to successfully credit your payment due to an error in our User Database. $supportEmail has been notified, and we will credit your payment as soon as possible.\n";

	       } else {
		  
	         if ($is_pending) {

                   $paypalSuccess = "Your Skotos account has been renewed at the $set_account level for $set_months months. We are still waiting for confirmation of the $payment_amount payment from your Paypal account, which will take 4 days. In the meantime, however, we've made your account immediately available. We'll let you know if there are any problems.";
		      
		 } else {
		    
  	           $paypalSuccess = "Your Skotos account has been renewed at the $set_account level for $set_months months. $acctCost has been deducted from your Paypal account. You may play the Skotos games immediately.";
		    
		 } # Pending or Regular Success Message
	       } # Complaint Failure or No Complaint Success Message
	     } # Pending or Record New Sale
	   } # Failure or Success in This Switch
	   break;

         default:

           $paypalError = "WARNING: Irregular Paypal Payment ($user_name: $item_name / $payment_status)\n";

       } # Step 3 (Switch)

    } # Step 2 (Paypal IPN)

  } # End: While !feof($fp)

  fclose ($fp);

} # Step 1 (Paypal Socket)

# Step 4: REPORT

if ($paypalError) {

# Step 4A:  Email Mistakes to CE

  sendEmailMessage("Support",$supportEmail,"PAYPAL Payment Error: $user_name",$paypalError,$complaint);

} 

if ($paypalUserError) {

  $paypalUserError .= $email_sig;

  sendEmailMessage($user_name,$payer_email,"$siteName/Paypal Payment ERROR",$paypalUserError,$complaint);

}

if ($paypalSuccess) {

  $paypalSuccess .= $email_sig;

  sendEmailMessage($user_name,$payer_email,"$siteName/Paypal Payment SUCCESS",$paypalSuccess,$complaint);

}

?>
