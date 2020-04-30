# TODO List for SkotOs Thin Auth Server

Following my initial work, I am leaving this server to the community. The following are my suggestions for improving it.

* <strike>Slow down polling of servers</strike> (added 60 second timeout on `socket_select`)
* Add optional link to main WWW pages
* Add CE/support pages
* Make location of files generic, rather than requiring `/var/www/html/user`
* Move non-authed commands from server-auth to server-control
* Add script or instructions to rotate `/var/log/userdb.log`
* Add watch script for userdb-autctl just in case
* Better center logo, especially on login page
