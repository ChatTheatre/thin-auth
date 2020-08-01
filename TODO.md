# TODO List for SkotOs Thin Auth Server

Following my initial work, I am leaving this server to the community. The following are my suggestions for improving it.

* <strike>Slow down polling of servers</strike> (added 60 second timeout on `socket_select`)
* <strike>Add optional link to main WWW pages</strike>
* <strike>Add CE/support pages</strike>
* Make location of files generic, rather than requiring `/var/www/html/user`
* Move non-authed commands from server-auth to server-control
* <strike>Add script or instructions to rotate `/var/log/userdb.log`</strike>
* Add watch script for userdb-autctl just in case
* <strike>Better center logo, especially on login page</strike>

## Bugs

The following are technically bugs, though they're not critical ones. Fixing them would improve the software.

* THe "list_flags()" function only works right when you're importing a database. There needs to be one example of each flag in the flags for it to work! (Maybe the samples should be set with ID 0? An alternative is just to list them:   return array("no-email","premium","grand","terms-of-service","deleted","banne\
* The Paypal payment address is case sensitive, where Paypal's addresses aren't. Currently, entering the address in all lower case makes it work fine, but it would be better to check the address case insensitively in `storypoints-paypal-verify.php` and `subscribe-paypal-verify.php`
