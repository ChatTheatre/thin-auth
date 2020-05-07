<?php

$pid = getmypid();
file_put_contents("/var/www/html/user/server-control.pid",$pid);

require_once("userdb.php");

$configInfo = read_config("server.json");

    $address = $configInfo['serverIP'];
    $port = $configInfo['serverCtlPort'];
    
    $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    socket_set_option($sock, SOL_SOCKET, SO_REUSEADDR, 1);
    socket_bind($sock, $address, $port);
    socket_listen($sock);

    $clients = array($sock);
    $write = NULL;
    $except = NULL;
    
    while (true) {
#	sleep(.1);    
        $read = $clients;

        if (socket_select($read, $write, $except, 60) < 1)
            continue;
       
        if (in_array($sock, $read)) {
            $clients[] = socket_accept($sock);
            $key = array_search($sock, $read);
            unset($read[$key]);
        }
       
        foreach ($read as $read_sock) {
            $data = @socket_read($read_sock, 1024, PHP_NORMAL_READ);

     	    if ($data === false) {
                $key = array_search($read_sock, $clients);
                unset($clients[$key]);
                echo "client disconnected.\n";
                continue;
            }
           
            $data = trim($data);

# THIS IS THE MAIN FUNCTIONALITY
# WHERE WE LOOK AT INPUT AND RESPOND

  	   $dataParts = explode(" ",$data);
	   if ($data == "") {
	   
	   } else if (sizeof($dataParts) < 3 || sizeof($dataParts) > 9) {

   	     $date = date('r');
	     error_log("[$date]: CTL: $data\n",3,"/var/log/userdb.log");
	     socket_error($read_sock,$seq,"BAD INPUT");
	      
	   } else {

   	     $date = date('r');
	     error_log("[$date]: CTL: $data\n",3,"/var/log/userdb.log");

	     for ($i = 0 ; $i < sizeof($dataParts) ; $i++) {
	       $dataParts[$i] = urldecode($dataParts[$i]);
	     }
 
             $dbh = load_db();

	     $command = $dataParts[0];
	     $seq = $dataParts[1];
	     $uname =$dataParts[2];
 	     $uid = lookup_uid_by_name($uname);
	     
	     switch ($command) {

## Account

		    case "account":

		    if (!$uid) {

		       socket_error($read_sock,$seq,"no such user");

		     } else {

		    	 $subcommand = $dataParts[3] . " " . $dataParts[4];
			 $flagsList = list_flags();

			 if ($subcommand == "clear status") {

			   $thisFlag = $dataParts[5];
			   
			   if (in_array($thisFlag,$flagsList)) {
  			     $return = delete_flag($uid,$thisFlag);
			   } else {
			     $return = delete_access($uid,$thisFlag);
			   }
			   
			   if ($return) {
			     socket_ok($read_sock,$seq,"CLEARED");
			   } else {
			     socket_error($read_sock,$seq,"");
			   }

			 } else if ($subcommand == "get status") {

			   $flags = query_all_status($uid);
			   socket_ok($read_sock,$seq,implode(" ",$flags));
			   
   		         } else if ($subcommand == "set status") {

			   $thisFlag = $dataParts[5];
			   
			   if (in_array($thisFlag,$flagsList)) {
   			     $return = set_flag_for_user($uid,$thisFlag);
			   } else {
   			     $return = set_access_for_user($uid,$thisFlag);
			   }

			   if ($return) {
			     socket_ok($read_sock,$seq,"SET");
			   } else {
			     socket_error($read_sock,$seq,"");
			   }
			   
		      }
		      }

		      break;

## AccountList

		  case "accountlist":

		       if ($dataParts[2] == "premium") {

		         $premList = account_with_flag("premium");
			 $premSplat = implode(" ",$premList);

			 socket_nok($read_sock,$seq,$premSplat);

		       } else if ($dataParts[2] == "premium/regular") {

		         $premList = regular_account_with_flag("premium");
			 $premSplat = implode(" ",$premList);

			 socket_nok($read_sock,$seq,$premSplat);

		       } else {

		         socket_error($read_sock,$seq,"Deprecated List");

		       }
		       break;
		       
		  
## Announce

		  case "announce":
		       socket_ok($read_sock,$seq,"OK");
		       break;
		       
## Billcredit

		   case "billcredit":

                        if (!$uid) {
			
                          socket_error($read_sock,$seq,"USER UNKNOWN");

			 } else {

			   bump_months($uid,$dataParts[3]);
			   socket_OK($read_sock,$seq,"OK");

			 }
			 break;
			 
## CE_Ban

		   case "ce_ban":


                        if (!$uid) {
			
                          socket_error($read_sock,$seq,"USER UNKNOWN");

			 } else {

			   if (sizeof($dataParts) < 5) {
			      $whom = "CE";
			    } else {
			      $whom = $dataParts[4];
			    }
			    
			    if (set_ban($uid,$dataParts[3],$whom)) {
			      socket_ok($read_sock,$seq,"BANNED");
			    } else {
 			      socket_error($read_sock,$seq,"NOT BANNED");
			    }
			 }
			 break;

## CE_Ban_Clear

		   case "ce_ban_clear":


                        if (!$uid) {
			
                          socket_error($read_sock,$seq,"USER UNKNOWN");

			 } else if (!is_banned($uid)) {

                          socket_error($read_sock,$seq,"USER NOT BANNED");

			 } else {
			    
			    if (delete_ban($uid)) {
			      socket_ok($read_sock,$seq,"BAN CLEARED");
			    } else {
 			      socket_error($read_sock,$seq,"BAN NOT CLEARED");
			    }
			 }
			 break;

## Checkping

		   case "checkping":
		   
                        if (!$uid) {

                          socket_error($read_sock,$seq,"USER UNKNOWN");
			  
			} else if (has_changed_email($uid)) {

			  socket_error($read_sock,$seq,"USER HAS NEW EMAIL");

			} else if (!has_verified_email($uid)) {

			  socket_error($read_sock,$seq,"USER HAS NO EMAIL");

			} else {

			  socket_OK($read_sock,$seq,"VALIDATED");

			}
			break;
		

## Cplayed

		   case "cplayed":

                        if (!$uid) {

                          socket_error($read_sock,$seq,"USER UNKNOWN");

			} else {

			  log_play($uid,$dataParts[3],$dataParts[4]);
			  socket_ok($read_sock,$seq,"NOTED");

			}
			break;
			
## Create

		    case "create":

		    if ($uid) {
		      socket_error($read_sock,$seq,"user exists");
		    } else if (!create_user($uname,$dataParts[3],$dataParts[4])) {
		       socket_error($read_sock,$seq,"Account creation failed");
		    } else {
                       $keycode = set_keycode_for_user(lookup_uid_by_name($uname));
                       socket_OK($read_sock,$seq,$keycode);
		    }
		    
		    
		    break;

## Deleteping

		    case "deleteping":

		    if (!$uid) {
		      socket_error($read_sock,$seq,"USER UNKNOWN");
		    } else {
		      $pinginfo = query_pinginfo($uid);
		      if (!$pinginfo) {
		        socket_error($read_sock,$seq,"NO PING");
		      }	else {
		        if ($dataParts[3]) {
    		          update_user_value($uid,"email",$pinginfo['email']);
			}
		        delete_ping($uid);
			socket_OK($read_sock,$seq,"DELETED");
		      }
		    }
		    break;

## Getprop
## (The Unprotected One)

	            case "getprop":

		    if (!$uid) {
		    
		      socket_error($read_sock,$seq,"USER UNKNOWN");
			  
                    } else {

		      $thisProp = query_property($uid,$dataParts[3]);
   		      socket_ok($read_sock,$seq,$thisProp);

		    }
		    break;
						      
## Logbill

		    case "logbill":

		    if (!$uid) {
		      socket_error($read_sock,$seq,"USER UNKNOWN");
		    } else {
		      if (sizeof($dataParts) < 7) {
		        $pending = 0;
		      } else {
		        $pending = $dataParts[6];
		      }
		      if (sizeof($dataParts) < 6) {
		        $cost = 0;
		      } else {
		        $cost = $dataParts[5];
		      }		      		      
                      if (log_purchase($uid,$dataParts[3],$dataParts[4],$cost,$pending)) {
		        socket_ok($read_sock,$seq,"LOGGED");
		      } else {
		        socket_error($read_sock,$seq,"NOT LOGGED");
                      }
		    }
		    break;

## Logpaypal

		    case "logpaypal":

		    if (!$uid) {
		      socket_error($read_sock,$seq,"USER UNKNOWN");
		    } else if (sizeof($dataParts) < 6) {
		      socket_error($read_sock,$seq, "MISSING TXNID");
		    } else {
		      if (sizeof($dataParts) < 7) {
		        $amount = 0;
		      } else {
		        $amount = $dataParts[6];
		      }		      
                      if (update_paypal_data($uid,$dataParts[5],$dataParts[3],$amount,$dataParts[4])) {
		        socket_ok($read_sock,$seq,"LOGGED");
		      } else {
		        socket_error($read_sock,$seq,"NOT LOGGED");
                      }
		    }
		    break;

## Paypalcheck

		   case "paypalcheck":

		   if (is_paypal_txnid_used($dataParts[2])) {
		     socket_error($read_sock,$seq,"TXNID ALREADY USED");
		   } else {
		     socket_ok($read_sock,$seq,"UNUSED");
		   }

		   break;
		   
## Setprop

		    case "setprop":

		    if (!$uid) {
		      socket_error($read_sock,$seq,"USER UNKNOWN");
		    } else {
		      if (sizeof($dataParts) < 5 || !$dataParts[4]) {
		        $return = delete_property($uid,$dataParts[3]);
		      } else {
		        $return = set_property($uid,$dataParts[3],$dataParts[4]);
		      }
		      if ($return) {
		        socket_ok($read_sock,$seq,"SET");
		      } else {
		        socket_error($read_sock,$seq,"NOTSET");
		      }
		    }

		    break;
		    
## Setpwd

	            case "setpwd":
	            case "setpwdg":
		    
		    $oldPass = $dataParts[3];
		    $newPass = $dataParts[4];
		    
		    if (!$uid) {

		       socket_error($read_sock,$seq,"no such user");

                    } else if ($command == "setpwd" &&
		               !is_password_OK($uid,$oldPass,$complaint)) {

		      socket_error($read_sock,$seq,"wrong old password");

                    } else if ($command == "setpwdg" &&
		               !is_guarantee_OK($uid,$oldPass,$complaint)) {

		      socket_error($read_sock,$seq,"BAD TOKEN");
		      
		    } else {

		      set_password_for_user($uid,$newPass);
		      socket_ok($read_sock,$seq,"SET");
		      
		    }
		    break;

## storypoints
		    case "sps":	     

		     if (!$uid) {

		       socket_error($read_sock,$seq,"no such user");

		     } else {

		       if ($dataParts[3] == "use") {
		       
		         $return = sps_add($uid,-$dataParts[4],$dataParts[5],"In-game Purchase","");
			 if ($return) {
			   socket_ok($read_sock,$seq,"USED");
			 } else {
			   socket_error($read_sock,$seq,"QUE?");
			 }

		       } else if ($dataParts[3] == "add") {

		         $return = sps_add($uid,$dataParts[4],$dataParts[5],"In-game Award","");
			 if ($return) {
			   socket_ok($read_sock,$seq,"ADDED");
			 } else {
			   socket_error($read_sock,$seq,"QUE?");
			 }

		       } else {
			     
	                 $sps = query_property($uid,"storypoints:" . $dataParts[3]);
 		         socket_ok($read_sock,$seq,$sps);
		       }
		
		     }
	             break;

		    case "storypoints":

		    if (!$uid) {

		       socket_error($read_sock,$seq,"no such user");

		     } else {
		     
			if ($dataParts[3] == "query") {

			  if ($dataParts[4] == "log:full" ||
			      $dataParts[4] == "log") {

### query log
### query log:full

			  $sps = sps_log($uid);
			  if (!$sps) {
			    return socket_ok($read_sock,$seq,"");
			  } else {

			    $sps_rt = "";
			    for ($i = 0 ; $i < sizeof($sps) ; $i++) {

			      if ($i != 0) {
			        $sps_rt .= " ";
			      }
			      
			      $sps_rt .= urlencode($sps[$i]['sp_date']);
			      $sps_rt .= " " . urlencode($sps[$i]['sp_value']);
			      $sps_rt .= " " . urlencode($sps[$i]['sp_who']);
			      $sps_rt .= " " . urlencode($sps[$i]['sp_reason']);
			      $sps_rt .= " " . urlencode($sps[$i]['sp_comment']);
			    }
			    socket_ok($read_sock,$seq,$sps_rt);
			  }

# purchased 27 days, 1 month, or 45 days, mostly

  			  } else if ($dataParts[4] == "purchased") {
			    if (sizeof($dataParts) > 5) {
			      $spSpan = $dataParts[5];
			    } else {
			      $spSpan = 0;
			    }
			    $sps = sps_purchased_recent($uid,$spSpan);
			    socket_ok($read_sock,$seq,$sps);
			  } else if ($dataParts[4] == "total") {
			    $sps = sps_total($uid);
			    socket_ok($read_sock,$seq,$sps);
			  } else if ($dataParts[4] == "used") {
			    $sps = sps_used($uid);
			    socket_ok($read_sock,$seq,$sps);			  
			  } else if ($dataParts[4] == "available") {
			    $sps = sps_available($uid);
			    socket_ok($read_sock,$seq,$sps);			  				  }

### add

			} else if ($dataParts[3] == "add") {

			  if (sizeof($dataParts) > 8) {
			    $comment = $dataParts[8];
			  } else {
			    $comment = "";
			  }
			  $return = sps_add($uid,$dataParts[4],$dataParts[5],$dataParts[6],$comment);
			  if ($return) {
			    socket_ok($read_sock,$seq,"ADDED");
			  } else {
			    socket_error($read_sock,$seq,"QUE?");
			  }
			}
                     }
		     break;
			 
## validate

		    case "validate":

		      if (lookup_uid_by_name($uname)) {
		      
		        socket_error($read_sock,$seq,"EXISTS");

		      } else if (!preg_match("/^[a-zA-Z]$/", $uname[0])) {

		        socket_error($read_sock,$seq,"INITIAL");

		      } else if (!preg_match('/^([a-zA-Z0-9_]+)$/',$uname)) {

		        for ($i = 1 ; $i < strlen($uname) ; $i++) {

                          if (!preg_match("/^[a-zA-Z0-9_]$/", $uname[$i])) {

  			    $thisError = "INVALID: " . ($i + 1);
			    socket_error($read_sock,$seq,$thisError);
			    break 2;
			    
			  }

			}
   		        socket_error($read_sock,$seq,"ILLEGAL CHARACTER");

		      } else {

		        socket_ok($read_sock,$seq,"OK");

                      }
		      
		    break;

		    case "statistics":

		      socket_ok($read_sock,$seq,"DEPRECATED");
		      break;
		      
		    default:
			socket_error($read_sock,$seq,"BAD COMMAND ($command)");
			break;
	     }
	     unset($dbh);

           }
	   
        } // end of reading foreach
    }

    // close the listening socket
    socket_close($sock);
?>
