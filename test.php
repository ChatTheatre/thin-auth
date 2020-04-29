<?php

require_once("userdb.php");
$dbh = load_db();

echo GetProperty($ctl_sock,"shannon_appel",$complaint,"creation_time") . "\n";
echo query_property(4,"creation_time") . "\n";

?>