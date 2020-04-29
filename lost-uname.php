<?

require_once("userdb.php");
$config = read_config("general.json");
$siteName = $config['siteName'];

$submit = $_POST['submit'];

if ($submit) {

  $email = $_POST['email'];
  checkEmail($auth_sock,$email,$uname,$complaint);

  if (!$complaint) {

    $userdbURL = $config['userdbURL'];
    
    $subject = "$siteName User Name";

      $body = "$uname,\n\n";
      $body .= "Your account name is $uname for $email.\n\n";
      $body .= "Login at https://$userdbURL/login.php?uname=$uname\n\n";
      $body .= "The $siteName team\n";

    sendEmailMessage($uname,$email,$subject,$body,$complaint);

    if (!$complaint) {
      $message = "Your account name has been emailed to $email.";
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
    Recover <? echo $siteName; ?> Account Name
  </div>
  
<? if ($complaint) {

  echo "<p><b><font color='red'>Error:</font></b> $complaint<br><br>";
  
    } else if ($message) {

  echo "<p><i>$message</i>";
  
    }
?>

<p><form action="<? echo $_SERVER['PHP_SELF']; ?>" method="post">
<input type="hidden" name="submit" value="1">
<table align="center">
  <tr>
    <td align="right">
      <b>Email Address:</b>
    </td>
    <td>
      <input type="text" name="email" id="email" size="24">
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
          </TD></TR>
</table>

</body>
</html>
