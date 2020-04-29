<?php

$pid = getmypid();
file_put_contents("/var/www/html/user/server-auth.pid",$pid);

require_once("userdb.php");

$configInfo = read_config("server.json");

    $address = $configInfo['serverIP'];
    $port = $configInfo['serverAuthPort'];
    
    $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    socket_set_option($sock, SOL_SOCKET, SO_REUSEADDR, 1);
    socket_bind($sock, $address, $port);
    socket_listen($sock);

    $clients = array($sock);
    $write = NULL;
    $except = NULL;
    
    while (true) {
        $read = $clients;

        if (socket_select($read, $write, $except, 0) < 1)
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


           if ($data) {

  	     $dataParts = explode(" ",$data);
	     for ($i = 0 ; $i < sizeof($dataParts) ; $i++) {
	       $dataParts[$i] = urldecode($dataParts[$i]);
	     }
	     
	     if ($data[0] == ":") {

	       $secureAuth = TRUE;
	       $uname = substr($dataParts[0],1);
	       $code = $dataParts[1];
	       $command = $dataParts[2];
	       $seq = $dataParts[3];
	       $dataArgs = array_slice($dataParts,4);
	       
	     } else {

	       $secureAuth = FALSE;
	       $command = $dataParts[0];
	       $seq = $dataParts[1];
	       $uname = $dataParts[2];
	       $code = FALSE;
	       $dataArgs = array_slice($dataParts,3);
	       
	     }
	     

	       
	     if (!$command || !$uname) {

	       $date = date('r');
               error_log("[$date]: AUTH: $data\n",3,"/var/log/userdb.log");
	     
	       socket_error($read_sock,$seq,"BAD INPUT");
	      
	     } else {


               $dbh = load_db();
	       $uid = lookup_uid_by_name($uname);

               $error = "";

	       if ($command == "passlogin") {

   	         $date = date('r');
                 error_log("[$date]: AUTH: recorded a passlogin for $uname\n",3,"/var/log/userdb.log");
		       
	       } else {

 	         $date = date('r');
                 error_log("[$date]: AUTH: $data\n",3,"/var/log/userdb.log");

               }

	       switch ($command) {

## convertaccount

		   case "convertaccount":

                        if (!is_user_ok($uid,$error)) {

                          socket_error($read_sock,$seq,$error);
			  
  			} else if ($code && !is_keycode_ok($uid,$code,$error)) {
			
                          socket_error($read_sock,$seq,$error);
			  
                        } else {

			  if ($dataArgs[0] == "premium") {
			    basic_to_premium($uid);
			    log_purchase($uid,"convert-to-premium",1,0,0);
			    socket_ok($read_sock,$seq,"premium");
			  } else if ($dataArgs[0] == "basic") {
			    delete_flag($uid,"premium");
			    log_purchase($uid,"convert-to-basic",1,0,0);
			    socket_ok($read_sock,$seq,"regular");			    
			  } else {
			    socket_error($read_sock,$seq,"Unknown conversion ($dataArgs[0])");
			  }
			}
			break;

## EmailLookup
			  
		   case "emaillookup":

		   	$real_uname = lookup_uname_by_email($uname);
			if (!$real_uname) {
			
			  socket_error($read_sock,$seq,"no such email");

			} else {

			  socket_nok($read_sock,$seq,$real_uname);

			}
			break;

## Emailused

## This is probably deprecated, but was easy to include

		   case "emailused":

		   	$real_uname = lookup_uname_by_email($uname);
			if (!$real_uname) {
			
			  socket_error($read_sock,$seq,"no such email");

			} else {

			  socket_ok($read_sock,$seq,"YES");

			}


		   	break;

## GetPing

	            case "getping":

                        if (!is_user_ok($uid,$error)) {

                          socket_error($read_sock,$seq,$error);
			  
  			} else if ($code && !is_keycode_ok($uid,$code,$error)) {
			
                          socket_error($read_sock,$seq,$error);
			  
                        } else {

			  $pinginfo = query_pinginfo($uid);
			  if (!$pinginfo) {
			    socket_error($read_sock,$seq,"NO PING");
			  } else {
			    socket_nok($read_sock,$seq,$pinginfo['ID'] . " " . $pinginfo['email'] . " " . $pinginfo['code']);
			  }
			}
			break;
			
## GetProp
## (The Protected One)

	            case "getprop":

                        if (!is_user_ok($uid,$error)) {

                          socket_error($read_sock,$seq,$error);
			  
  			} else if ($code && !is_keycode_ok($uid,$code,$error)) {
			
                          socket_error($read_sock,$seq,$error);
			  
                        } else {

			  if ($secureAuth) {
			    $qProp = $dataArgs[1];
			  } else {
			    $qProp = $dataArgs[0];
			  }
			  
			  $thisProp = query_property($uid,$qProp);
			  socket_ok($read_sock,$seq,$thisProp);

			}
			break;
## KEYCODE AUTH

	     	    case "keycodeauth":

		        if (!$code) {
			  $code = $dataArgs[0];
			}
			
                        if (!is_user_ok($uid,$error)) {

                          socket_error($read_sock,$seq,$error);

  			} else if (!is_keycode_ok($uid,$code,$error)) {

                          socket_error($read_sock,$seq,$error);

			} else if (!is_tossed($uid)) {

                          socket_error($read_sock,$seq,"TOS");

			} else if (!has_verified_email($uid)) {

                          socket_error($read_sock,$seq,"USER HAS NO EMAIL");
			  
			} else {

    	    		  $user_type = lookup_account_type($uid);
			  $user_status = query_property($uid,"account_status");
			  $user_status = str_replace(","," ",$user_status);
			    
			  $user_string = "($user_type;$user_status)";


  	    		  if (is_paid($uid)) {

			    if ($user_type == "developer" ||
			        $user_type == "staff" ||
				$user_type == "free") {

				  socket_nok($read_sock,$seq,"PAID 0 $user_string");

			     } else if ($user_type == "trial") {

			       $nextStamp = lookup_next_stamp($uid);
			       socket_nok($read_sock,$seq,"TRIAL $nextStamp $user_string");

			     } else {
			     
			       $nextStamp = lookup_next_stamp($uid);
			       socket_nok($read_sock,$seq,"PAID $nextStamp $user_string");
			     }
			    
                           } else {
			   
  			     socket_OK($read_sock,$seq,"UNPAID $user_string");
                           }

			}
			break;

## MD5Login

		   case "md5login":

                        if (!is_user_ok($uid,$error)) {

                          socket_error($read_sock,$seq,$error);

                        } else if (!is_hash_ok($uid,$dataArgs[0],$error)) {

			  socket_error($read_sock,$seq,$error);

			} else {

			  $keycodeinfo = get_keycode_for_user($uid);
			  socket_ok($read_sock,$seq,$keycodeinfo['keycode']);

			}
			break;
			
## PASSLOGIN

	     	    case "passlogin":

                        if (!is_user_ok($uid,$error)) {

                          socket_error($read_sock,$seq,$error);

                        } else if (!is_password_ok($uid,$dataArgs[0],$error)) {

                          socket_error($read_sock,$seq,$error);

			} else {
			  $keycode = set_keycode_for_user($uid);
			  socket_OK($read_sock,$seq,$keycode);
			}
			break;

## PINGUSER

	   case "pinguser":

                        if (!is_user_ok($uid,$error)) {

                          socket_error($read_sock,$seq,$error);
			  
  			} else if ($code && !is_keycode_ok($uid,$code,$error)) {

                          socket_error($read_sock,$seq,$error);

			} else {

			  if (ping_user($uid,$dataArgs[0])) {
			    if (mail_ping($uid)) {
			      socket_OK($read_sock,$seq,"OK");
			    } else {
			      socket_error($read_sock,$seq,"email failed");
			    }
			  } else {
			    socket_error($read_sock,$seq,"ping failed");
			  }
			}
			
			break;

## Setemail

	   case "setemail":


                        if (!is_user_ok($uid,$error)) {
			
                          socket_error($read_sock,$seq,$error);

                        } else {

			  update_user_value($uid,"email",$dataArgs[0]);
			  socket_OK("YES");
			  
			}
			break;
			
## Tempkeycode

	   case "tempkeycode":

                        if (!is_user_ok($uid,$error)) {
			
                          socket_error($read_sock,$seq,$error);

			} else {
			
			  $keycode = set_keycode_for_user($uid,1);
			  socket_OK($read_sock,$seq,$keycode);
                        }
			break;

## Tempguarantee

	   case "tempguarantee":

                        if (!is_user_ok($uid,$error)) {
			
                          socket_error($read_sock,$seq,$error);

			} else {
			  $guarantee = gen_keycode_guarantee($uid);
			  socket_OK($read_sock,$seq,$guarantee);
                        }
			break;

		    default:
			socket_error($read_sock,$seq,"BAD COMMAND ($command)");
			break;
	     }

	     unset($dbh);

           }
	 }

        } // end of reading foreach
    }

    // close the listening socket
    socket_close($sock);
?>
