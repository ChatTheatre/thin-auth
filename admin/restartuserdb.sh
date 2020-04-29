#!/bin/bash

cd /var/www/html/user
if [ -f server-auth.pid ]
then

  authpid=`cat server-auth.pid`
  echo "CHECKING $authpid";
  
  if [ ! -d /proc/$authpid ]
  then
      /usr/bin/php server-auth.php &

  fi

else

  /usr/bin/php server-auth.php &

fi

if [ -f server-control.pid ]
then

  authpid=`cat server-control.pid`
  echo "CHECKING $authpid";
  
  if [ ! -d /proc/$authpid ]
  then
      /usr/bin/php server-control.php &

  fi

else

  /usr/bin/php server-control.php &

fi

