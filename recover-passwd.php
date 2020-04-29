<?

   $uname = urldecode($_GET['uname']);
   $code = $_GET['code'];
   $token = $_GET['token'];
   
   if (!$uname || !$code || !$token) {
   
     Header("Location: login.php");
   }

   require_once("userdb.php");
   $config = read_config("general.json");
   $cookieURL = set_cookie_url();

   setcookie("user", $uname, 0, "/", $cookieURL);
   setcookie("pass", $code,  0, "/", $cookieURL);
 
   Header("Location: change-passwd.php?token=$token");

?>