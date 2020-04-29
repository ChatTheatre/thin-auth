<?

require_once("userdb.php");
$config = read_config("general.json");
$siteName = $config['siteName'];

$submit = $_POST['submit'];

if ($submit) {

  $uname = $_POST['uname'];
  $uid = getProperty($ctl_sock, $uname, $complaint, "ID");

  if (!$uid) {
    $complaint = "No such user.";

  } else {

    $keycode = requestTempKeycode($auth_sock,$uname,$user,$pass, $guarantee, $complaint);

    if (!$complaint) {

      $email = getProperty($ctl_sock,$uname,$complaint,"email");

      $subject = "Lost $siteName Password";

      $body = "$uname,\n\n";
      $body .= "You can update your password at https://" . $config['userdbURL'] . "/recover-passwd.php?uname=" . urlencode($user) . "&code=$pass&token=$guarantee\n\n";
      $body .= "The $siteName team\n";

       sendEmailMessage($uname,$email,$subject,$body,$complaint);
       $message = "Your recovery link has been sent.";
       
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
    <b>Recover <? echo $siteName; ?> Password</b>
  </div>

<? if ($complaint) {

  echo "<p><b><font color='red'>Error:</font></b> $complaint<br><br>";
  
    } else if ($message) {

  echo "<p><i>$message</i>";
  
    }
?>

<form action="<? echo $_SERVER['PHP_SELF']; ?>" method="post">
<input type="hidden" name="submit" value="1">
<p align="center"><table>
  <tr>
    <td align="right">
      <b>Account Name:</b>
    </td>
    <td>
      <input type="text" name="uname" id="uname" size="24">
    </td>
  </tr>
  <tr>
    <td colspan=2 align="right">
      <input type="submit">
    </td>
  </tr>
</table>
</form>
</div>
<br><br>
<div class="acctinfo subinfo" align="center">
            <p><font face="arial,helvetica"><b>Forgot your account name?</b><br>
            We can <A HREF="lost-uname.php">Email</A> that to you too.</P>
</div>
    </td>
  </tr>
</table>  
</body>
</html>
