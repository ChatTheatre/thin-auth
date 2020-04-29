<?

  require_once("userdb.php");
  $config = read_config("general.json");

  $cookieURL = set_cookie_url();

  setcookie("user", "",   time() - 3600 * 24, "/", $cookieURL);
  setcookie("pass", "",   time() - 3600 * 24, "/", $cookieURL);

  Header("Location: login.php");

	
?>
