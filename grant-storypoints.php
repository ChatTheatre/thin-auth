<?php

require_once("userdb.php");

$config = read_config("general.json");
$siteName = $config['siteName'];
$fconfig = read_config("financial.json");
$sps = $fconfig['monthlyPremiumSPs'];

if ($sps) {

if (file_exists("grant-storypoints.lid")) {
  $lastGrant = file_get_contents("grant-storypoints.lid");
} else {
  $lastGrant = "00-0000";
}  

list($lastMo,$lastYr) = explode("-",$lastGrant);

$curGrant = date('m-Y',mktime());
list($curMo,$curYr) = explode("-",$curGrant);

if (($curYr > $lastYr) ||
    ($curYr == $lastYr && $curMo > $lastMo)) {

  $list = accountList($ctl_sock,"premium/regular",$complaint);
  for ($i = 0 ; $i < sizeof($list); $i++) {
    $thisUser = $list[$i];

    storypoints_add($ctl_sock,$thisUser,$sps,"Monthly Premium",$siteName,"",$complaint);
    echo "Granting $sps SPs to $thisUser\n";
    
  }
  
  file_put_contents("grant-storypoints.lid","$curMo-$curYr");
  
}

}


?>
