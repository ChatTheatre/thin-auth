<?

  require_once("userdb.php");
  $config = read_config("general.json");
  $siteName = $config['siteName'];

  $user = $_COOKIE["user"];
  $pass = $_COOKIE["pass"];

  if ($user == "" || checkTimeOut($auth_sock, $user, $pass, $complaint)) {
     Header("Location: login.php?timeout=1&timeout_from=" .
	    urlencode($_SERVER['PHP_SELF'] . ($_SERVER['QUERY_STRING'] ? "?" . $_SERVER['QUERY_STRING'] : "")));
     exit;
  }

  $isAdmin = isAdmin($auth_sock,$user,$pass,$complaint);
  if (!$isAdmin) {
    Header("Location: overview.php");
    exit;
  }
  
  $action = $_POST['action'];
  if ($action) {

    $viewUser = $_POST['viewUser'];
    $viewUserEncoded = urlencode($viewUser);
    
# Check that user exists

    $uid = getProperty($ctl_sock,$viewUser,$complaint,"ID");

    if (!$complaint) {
    
      switch($action) {

        case "add-play";
          $playTime = $_POST['playTime'];
	  list($thisTime,$thisUnit) = explode(" ",$playTime);

          if ($thisUnit == "days") {
	    billingCreditDays($ctl_sock,$viewUser,$thisTime,$user,$complaint);
	  } else if ($thisUnit == "months") {
	    billingCredit($ctl_sock,$viewUser,$thisTime,$user,$complaint);
	  } else {
	    $metaComplaint = "Illegal time unit $thisUnit for credit";
	  }
	  break;

	case "add-sps";

	  $spAmt = $_POST['spAmt'];
	  $spReason = $_POST['spReason'];
	  $spWhom = $_POST['spWhom'];
	  
	  if (!$spAmt) {
	    $metaComplaint = "You must enter a StoryPoint grant amount.";
          } else if (!$spReason) {
	    $metaComplaint = "You must enter a reason for the Storypoint grant.";
          } else if (!$spWhom) {
	    $metaComplaint = "You must enter an origin for the Storypoint grant.";
          } else {
	    storypoints_add($ctl_sock,$viewUser,$spAmt,$spReason,$spWhom,"",$complaint);
	  }
	  break;
	  
        case "ban-user";
 
          $banReason = $_POST['banReason'];
	  if ($banReason) {
	    banUser($ctl_sock,$viewUser,$banReason,$user,$metaComplaint);
	  } else {
	    $metaComplaint = "You must give a reason for banning $viewUser.";
	  }
	  break;
	
        case "unban-user":
 
	  unbanUser($ctl_sock,$viewUser,$metaComplaint);
	  break;


        case "change-user":

## Set Type

  	  $oldType = getProperty($ctl_sock,$viewUser,$complaint,"account_type");
          $type = $_POST['type'];
	  
	  if ($type && $oldType != $type) {
	    setProperty($ctl_sock,$viewUser,$complaint,"account_type",$type);
	  }

## Set Email

  	  $oldEmail = getProperty($ctl_sock,$viewUser,$complaint,"email");
          $email = $_POST['email'];
	  
	  if ($email && $oldEmail != $email) {
	    setProperty($ctl_sock,$viewUser,$complaint,"email",$email);
	  }
	  
## Set Access

	  $newPrivs = $_POST['privs'];
          $oldPrivs = getAccountStatus($ctl_sock,$viewUser,$config['gameID'],$complaint);

	  if ($newPrivs == "admin") {
	    $privs = TRUE;
	    if (!$oldPrivs) {
	    
	      setAccountStatus($ctl_sock, $viewUser, $config['gameID'], $complaint);

	    }
	  } else if ($newPrivs == "none") {
	    $privs = FALSE;
	    if ($oldPrivs) {

	      clearAccountStatus($ctl_sock, $viewUser, $config['gameID'], $complaint);
	      
	    }
	  }

# Set Flags

	  $banned = $_POST['banned'];
	  $deleted = $_POST['deleted'];
	  $grand = $_POST['grand'];
	  $noemail = $_POST['noemail'];
	  $premium = $_POST['premium'];
	  
	  $status = getAccountStatus($ctl_sock,$viewUser,"all",$complaint);
	  
	  $oldBanned = in_array("banned",$status);
	  $oldDeleted = in_array("deleted",$status);
	  $oldGrand = in_array("grand",$status);
	  $oldNoemail = in_array("no-email",$status);
	  $oldPremium = in_array("premium",$status);

	  if ($banned != $oldBanned) {
	    if ($banned) {
	      banUser($ctl_sock,$viewUser,"Banned","Staff",$complaint);
	    } else {
	      unbanUser($ctl_sock,$viewUser,$complaint);
	    }
	  }
	  
	  if ($deleted != $oldDeleted) {
	    if ($deleted) {
	      setAccountStatus($ctl_sock,$viewUser,"deleted",$complaint);
	    } else {
	      clearAccountStatus($ctl_sock,$viewUser,"deleted",$complaint);
	    }
	  }

	  if ($grand != $oldGrand) {
	    if ($grand) {
	      setAccountStatus($ctl_sock,$viewUser,"grand",$complaint);
	    } else {
	      clearAccountStatus($ctl_sock,$viewUser,"grand",$complaint);
	    }
	  }

	  if ($noemail != $oldNoemail) {
	    if ($noemail) {
	      setAccountStatus($ctl_sock,$viewUser,"no-email",$complaint);
	    } else {
	      clearAccountStatus($ctl_sock,$viewUser,"no-email",$complaint);
	    }
	  }

	  if ($premium != $oldPremium) {
	    if ($premium) {
	      setAccountStatus($ctl_sock,$viewUser,"premium",$complaint);
	    } else {
	      clearAccountStatus($ctl_sock,$viewUser,"premium",$complaint);
	    }
	  }

	  break;
	
      }

# This is effectively 'case "select-user"', but it goes off after all actions

      $complaint = $metaComplaint;
	  
      $email = getProperty($ctl_sock,$viewUser,$complaint,"email");
      $type = getProperty($ctl_sock,$viewUser,$complaint,"account_type");
      $privs = getAccountStatus($ctl_sock,$viewUser,$config['gameID'],$complaint);
	  
      $status = getAccountStatus($ctl_sock,$viewUser,"all",$complaint);
	  
      $banned = in_array("banned",$status);
      if ($banned) {
        $bwho = getProperty($ctl_sock,$viewUser,$complaint,"banned:who");
        $bwhenUNIX = getProperty($ctl_sock,$viewUser,$complaint,"banned:when");
        $bwhen = date("M d, Y",$bwhenUNIX);
        $bwhy = getProperty($ctl_sock,$viewUser,$complaint,"banned:reason");
      }
	  
      $deleted = in_array("deleted",$status);
      $grand = in_array("grand",$status);
      $noemail = in_array("no-email",$status);
      $premium = in_array("premium",$status);

      $creationUNIX = getProperty($ctl_sock,$viewUser,$complaint,"creation_time");
      $creation = date("M d, Y",$creationUNIX);

      if ($type == "regular") {
        $daysLeft = getProperty($ctl_sock, $viewUser, $complaint, "paiddays");
      } else if ($type == "trial") {
        $daysLeft = getProperty($ctl_sock, $viewUser, $complaint, "trialdays");
      }
      if (($type == "regular" || $type == "trial") && !$daysLeft) {
        $daysLeft = "expired";
      }

      $spAvail = getProperty($ctl_sock,$viewUser,$complaint,"storypoints:available");
 
    }	
    
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
    <td align="center">

<!-- Inner Table: Left Column -->
<? if (!$uid) { ?>

      <div class="acctinfo">
      <div class="titlebar">
        User Selector
      </div>
      <p><form action="<? echo $url; ?>" method="post">
      <input type="hidden" name="action" value="select-user">
      <b>User:</b> <input type="text" name="viewUser" value="<? if ($viewUser) echo $viewUser; ?>">&nbsp;&nbsp;&nbsp;&nbsp;
      <input type="submit">
      </form>
      </div>
      <div class="acctinfo">
          <i>go to: <a href="support-report.php">report page</a> •
          <a href="support-mail.php">email page</a> •
          <a href="overview.php">your overview</a></i>
     </div>


<? } ?>

<? if ($uid) { ?>

      <div class="acctinfo">
      <div class="titlebar">
        User Info
      </div>
      <form action="<? echo $url; ?>" method="post">
      <p><table width='100%'>
      <input type="hidden" name="action" value="change-user">
        <tr>
	  <td align='right'>
            <b>Account:</b>
	  </td>
	  <td align='left'>
	    <input type="hidden" name="viewUser" value="<? echo $viewUser; ?>">
	    <? echo $viewUser; ?> [ #<? echo $uid; ?> ]
	  </td>
	</tr>
        <tr>
	  <td align='right'>
            <b>Created:</b>
	  </td>
	  <td align='left'>
	    <? echo $creation; ?>
	  </td>
	</tr>		
        <tr>
	  <td align='right'>
            <b>Email:</b>
	  </td>
	  <td align='left'>
	    <input type="text" size=40 name="email" value="<? echo $email; ?>">
	  </td>
	</tr>	
	<tr><td colspan='2'><br></td></tr>
	<tr>
	  <td colspan='2'>
	    <div class="titlebar subbar">
	      <b>Account Rights</b>
	    </tiv>
	  </td>
	</tr>
	<tr><td colspan='2'><br></td></tr>	
	<tr>
	  <td align='right'>
 	    <b>Type:</b>
	  </td>
	  <td align='left'>
	    <select name="type">
<?
   $possible_types = array("regular","trial","free","developer","staff");
   
   for ($i = 0 ; $i < sizeof($possible_types) ; $i++) {

      if ($possible_types[$i] == $type) {
      
        echo "<option value=\"$possible_types[$i]\" SELECTED>" .
	     ucfirst($possible_types[$i]) . " Account";

} else {

        echo "<option value=\"$possible_types[$i]\">" .
	     ucfirst($possible_types[$i]) . " Account";

      }
    }
?>
	    </select>
	  </td>
	</tr>
        <tr>
	  <td align='right'>
	    <b>Access:</b>
	  </td>
	  <td align='left'>
	    <select name="privs">
<?
  if ($privs) {
?>
              <option value="admin" SELECTED>Admin Access
	      <option value="none">No Rights
<?
  } else {
?>
              <option value="admin">Admin Access
	      <option value="none" SELECTED>No Rights
<?
  }
?>
	    </select>
	  </td>
	</tr>
	<tr><td colspan='2'><br></td></tr>
	<tr>
	  <td colspan='2'>
	    <div class="titlebar subbar">
	      <b>Account Flags</b>
	    </tiv>
	  </td>
	</tr>
	<tr>
	  <td colspan=2>
	    <table align='center' width='80%'>
	      <tr>
	        <td>
<? if ($banned) { ?>	  
  	          <input type='checkbox' name='banned' value='1' CHECKED>
<? } else { ?>
	          <input type='checkbox' name='banned' value='1'>
<? } ?>
                </td>
	        <td>
                  Banned
	        </td>
	        <td>
<? if ($deleted) { ?>	  
	          <input type='checkbox' name='deleted' value='1' CHECKED>
<? } else { ?>
	          <input type='checkbox' name='deleted' value='1'>
<? } ?>
                </td>
	        <td>
                  Deleted
	        </td>
`	      </tr>
              <tr>
	        <td>
<? if ($grand) { ?>	  
	          <input type='checkbox' name='grand' value='1' CHECKED>
<? } else { ?>
	          <input type='checkbox' name='grand' value='1'>
<? } ?>
                </td>
	        <td>
                  Grandfathered
	        </td>
	        <td>
<? if ($noemail) { ?>	  
	          <input type='checkbox' name='noemail' value='1' CHECKED>
<? } else { ?>
	          <input type='checkbox' name='noemail' value='1'>
<? } ?>
                </td>
	        <td>
                  No Email
	        </td>		
	      </tr>
              <tr>
	        <td>
<? if ($premium) { ?>	  
	          <input type='checkbox' name='premium' value='1' CHECKED>
<? } else { ?>
	          <input type='checkbox' name='premium' value='1'>
<? } ?>
                </td>
	        <td>
                  Premium
	        </td>	      
	    </table>
	  </td>
	</tr>
      </table>
      <p><input type="submit" value="Change Info">      
      </form>
      </div>

      <div class="acctinfo">
          <i>view <? echo $viewUser; ?>'s:
	    <a href="billing.php?id=<? echo $viewUserEncoded; ?>">bills</a> •
	    <a href="play.php?id=<? echo $viewUserEncoded; ?>">plays</a> •	  	    
	    <a href="storypoints.php?id=<? echo $viewUserEncoded ?>">sps</a>
          </i>
     </div>
     
      <div class="acctinfo">
          <i>return to: <a href="support.php">support page</a> •
	  <a href="overview.php">your overview</a></i>
     </div>

    </td>

<!-- Inner Table: Right Column -->

    <td align="center">

<? if ($type == "trial" || $type == "regular") { ?>

      <div class="acctinfo">
      <div class="titlebar">
        Add Play Time
      </div>
      <form action="<? echo $url; ?>" method="post">
      <p><table width='100%'>
      <input type="hidden" name="action" value="add-play">
      <input type="hidden" name="viewUser" value="<? echo $viewUser; ?>">
      <tr>
        <td align='right'>
	  <b>Days Left:</b>
	</td>
	<td align='left'>
	  <? echo $daysLeft; ?>
	  <? if ($daysLeft != "expired" && $type == "regular" && $premium) echo " (premium)"; ?>
	</td>
      </tr>	  
      <tr>
        <td align='right'>
          <b>Add:</b>
	</td>
        <td align='left'>  	
          <select name="playTime">
            <option value="1 days">1 day
            <option value="3 days">3 days
            <option value="7 days">7 days
            <option value="14 days">14 days
            <option value="1 months">1 month
            <option value="3 months">3 months
            <option value="12 months">12 months
          </select>
	</td>
      </tr>
      </table>
      <p><input type="submit" value="Add Time">            
      </form>
      </div>
<? } ?>

      <div class="acctinfo">
      <div class="titlebar">
        Add StoryPoints
      </div>
      <form action="<? echo $url; ?>" method="post">
      <p><table width='100%'>
      <input type="hidden" name="action" value="add-sps">
      <input type="hidden" name="viewUser" value="<? echo $viewUser; ?>">
      <tr>
        <td align='right'>
	  <b>Current SPs:</b>
	</td>
	<td align='left'>
	  <? echo $spAvail; ?>
	</td>
      </tr>	  
      <tr>
        <td align='right'>
          <b>Add:</b>
	</td>
        <td align='left'>
	  <input type="text" name="spAmt" value="50" size=8>
	</td>
      </tr>
      <tr>
        <td align='right'>
          <b>Reason:</b>
	</td>
        <td align='left'>
	  <input type="text" name="spReason">
	</td>
      </tr>
      <tr>
        <td align='right'>
          <b>From:</b>
	</td>
        <td align='left'>
	  <input type="text" name="spWhom" value="<? echo $user; ?>">
	</td>
      </tr>            
      </table>
      <p><input type="submit" value="Add SPs">            
      </form>
      </div>
      
<? if ($banned) { ?>

      <div class="acctinfo">
      <div class="titlebar">
        Unban User
      </div>
      <p><form action="<? echo $url; ?>" method="post">
      <input type="hidden" name="action" value="unban-user">
      <input type="hidden" name="viewUser" value="<? echo $viewUser; ?>">
      <? if ($bwho) { ?>
        <b>Banned by <? echo $bwho; ?> (<? echo $bwhen; ?>)</b><br>
      <? } else { ?>
        <b>Banned by <? echo $bwhen; ?></b><br>      
      <? } ?>
      <i><? echo $bwhy; ?></i>
      <p><input type="submit" value="Unban User!">
      </form>
      </div>

<? } else { ?>

      <div class="acctinfo">
      <div class="titlebar">
        Ban User
      </div>
      <p><form action="<? echo $url; ?>" method="post">
      <input type="hidden" name="viewUser" value="<? echo $viewUser; ?>">
      <input type="hidden" name="action" value="ban-user">
      <b>Reason:</b> <input type="text" name="banReason" size=40>
      <p><input type="submit">
      </form>
      </div>

<? } ?>

    </td>

<? } ?>

  </tr>
</table>
