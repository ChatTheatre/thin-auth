<?

require_once("userdb.php");

$config = read_config("general.json");
$siteName = $config['siteName'];
$cookieURL = set_cookie_url();

$submit = $_POST['submit'];
$not18 = $_COOKIE['not18'];

if ($submit) {

  $uname = $_POST['uname'];
  $age = $_POST['age'];
  $email1 = $_POST['email1'];
  $email2 = $_POST['email2'];
  $pwd1 = $_POST['pwd1'];
  $pwd2 = $_POST['pwd2'];
  $tos = $_POST['tos'];

  if (!$uname || !$age || !$email1 || !$email2 || !$pwd1 || !$pwd2) {

    $complaint = "You must fill in all fields.";

  } else if (!$tos) {

    $complaint = "You must agree to the Terms of Service.";

  } else if (strlen($uname) < 4) {

    $complaint = "The account name must be at least 4 characters long.";

  } else if (strlen($uname) > 30) {

    $complaint = "The account name must no more than 30 characters long.";
    
  } else if (!validateUsername($ctl_sock,$uname,$complaint)) {

    $complaint = urldecode($complaint);
    
# complaint set automatically

  } else if ($age < 18) {

    $complaint = "Sorry, you must be 18 or older.";
    setcookie("not18","true",0,"/",$cookieURL);
    $not18 = TRUE;
    
  } else if ($email1 != $email2) {

    $complaint = "Emails do not match.";
    $email1 = $email2 = "";

  } else if (checkEmail($auth_sock,$email1,$uname,$XXX)) {

    $complaint = "Email address $email1 is already in use";
    $email1 = $email2 = "";
    
  } else if ($pwd1 != $pwd2) {

    $complaint = "Passwords do not match.";

  } else {

    createUser($ctl_sock,$uname,$pwd1,$user,$pass,$email1,$complaint);
    
    if (!$complaint) {

      if ($tos) {
        setAccountStatus($ctl_sock,$uname,"terms-of-service",$complaint);
      }
      pingUser($auth_sock,$uname,$email1,$pass,$complaint);

      if (!$complaint) {

        $message = "Account successfully created! Watch your email for a verification message, and check your spam folder if you don't see it.";

	$uname = $age = $email1 = $email2 = "";
	
      }
      
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
          Create <? echo $siteName; ?> Account
	</div>
<? if ($complaint) {

  echo "<p><b><font color='red'>Error:</font></b> $complaint<br><br>";
  
    } else if ($message) {

  echo "<p><i>$message</i>";
  
    }
?>
<? if (!$not18) { ?>

<p><form action="<? echo $_SERVER['PHP_SELF']; ?>" method="post">
<input type="hidden" name="submit" value="1">
<table>
  <tr>
    <td align="right">
      <b>Account Name:</b>
    </td>
    <td>
      <input type="text" name="uname" id="uname" value="<? echo $uname; ?>" size="24">
    </td>
  </tr>
  <tr>
    <td align="right">
      <b>Age:</b>
    </td>
    <td>
      <input type="text" name="age" id="age" value="<? echo $age; ?>" size="8">
    </td>
  </tr>    
  <tr>
    <td align="right">
      <b>Email Address:</b>
   </td>
    <td>
      <input type="text" name="email1" id="email1" value="<? echo $email1; ?>" size="32">
    </td>
  </tr>
  <tr>
    <td align="right">
      <b>Re-Type Email Address:</b>
   </td>
    <td>
      <input type="text" name="email2" id="email2" value="<? echo $email2; ?>" size="32">
    </td>
  </tr>  
  <tr>
    <td align="right">
      <b>Password:</b>
   </td>
    <td>
      <input type="password" minlength="8" name="pwd1" id="pwd1" size="24">
    </td>
  </tr>
  <tr>
    <td align="right">
      <b>Re-Type Password:</b>
   </td>
    <td>
      <input type="password" minlength="8" name="pwd2" id="pwd2" size="24">
    </td>
  </tr>
  <tr>
    <td colspan=2>
      <input type="checkbox" id="tos" name="tos" value="1">I have read and agree to the <a href="tos.php" target="_blank">Terms of Service</a>.
  <tr>
    <td colspan=2 align="right">
      <input type="submit">
    </td>
  </tr>
<? } #not18 protection ?>

</table>
</form>

</div>
          </TD></TR>
</table>

</body>
</html>
