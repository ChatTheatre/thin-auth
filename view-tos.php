<?

include("userdb.php");

$config = read_config("general.json");

?>
<html>
<head>
<link rel="stylesheet" href="assets/login.css">
</head>
<body>
<h1><? echo $config['siteName']; ?> Terms of Service</h1>
<?

include("assets/tos.txt");

?>
</body>
</html>