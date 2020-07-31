# The SkotOS Thin Auth Server

The thin auth server is intended to provide logins to SkotOS games, while also supporting payments and a variety of user information. Configuration files help to individualize it for different machines, though fundamentally this is a SkotOS-focused server as it currently stands.

The thin auth server is offered as an alternative to the UserDB built directly from SkotOS. It's meant to be simpler, more accessible, and easier to administer (and modify).

See the [TODO list](TODO.md) for possible improvements.

## Installing the Thin Auth Server 

To install the thin auth server requires the following steps, which are demonstrated on a Debian system.

### 1. Retrieve the Thin Auth Server from GitHub

Clone the repo onto your game server:
```
# git clone https://github.com/skotostech/thin-auth /var/www/html/user
```

Note that we currently require installation in `/var/www/html/user`. This could be changed, with `server-admin.php`, `server-control.php`, and `admin/restartuserdb.sh` requiring updates. (Making this more generic is on the [TODO list](TODO.md).) Obviously, you'll need to adjust later instructions, such as the setup of `apache` too.

(If there's an error here, it may be because you don't currently have an HTML file structure; you can install `apache2` first, as noted in Step 3, or just create `/var/www/html` by hand.)

#### 1A. Copy the Config Files

Config files for the server are stored in the `config` directory. You'll need to move each of the `SAMPLE`s to the correct installed name:
```
# mv database.json-SAMPLE database.json
# mv financial.json-SAMPLE financial.json
# mv general.json-SAMPLE general.json
# mv server.json-SAMPLE server.json
```
You also want to edit these files as appropriate for your game:

*database.json:* This contains access information for your MariaDB. If you create things using the default `userdb` database name and user name, all you need to change is the password, `dbPass`, which should correspond to your user.

*financial.json:* This contains financial information for paying for access to your game. The only one that must be changed is `paypalAcct`, your Paypal account email address. You can also change: the costs for subscriptions or storypoints; the maximum number of Storypoints that can be purchased each month; and  how days are converted when a user moves from a basic account to a premium account. (For a `premiumToBasicConversion` of `n`, the days are multiplied by `1/n`.)

*general.json:* This contains all of your game's specific identifying information. Record the URLs of these UserDB pages and the game, the site logo that will be placed in `assets` and a few other things. Expect to change everything in this file, which has been left set to the default of the defunct Lovecraft Country game as an example.

*server.json:* This contains the connection information for the UserDB server, which accepts socket connections from a game and from web pages. By default it runs at ports 9970 and 9971 on localhost. If you are using this default setup, no additional work is required. The UserDB *is* set up so that the web pages, the server, and the game do not have to be on the same machine. If you choose such a setup, you'll need to install the UserDB software on multiple machines; that is left as an exercise for the system administrator.

### 2. Prepare Your DNS

The next step in preparation is to create appropriate DNS records for your database. Our suggestion is to choose a domain, such as `skot-os.com` and then create four different subdomains. Host web pages on `www.skot-os.com`, host the [Orchil Game Client](https://github.com/skotostech/orchil) on `client.skot-os.com`, host the [SkotOS game](https://github.com/skotostech/SkotOS) on `game.skot-os.com`, and finally host this login and UserDB server on `login.skot-os.com`.

Ultimately, you can use any setup you want.

For the moment, create the DNS records for your login/userdb server. After you edit a file such as `/etc/bind/master/you-domain`, you should also run:
```
# /etc/init.d/bind9 restart
```
It may take minutes to hours for the new domain name to propagate, depending on your setup.

You will then need to record the name of your login/userdb as `userdbURL` in `config/general.json`, if you have not already.

### 3. Update Your System

You will need to install Apache, MariaDB, PHP, and related libraries

```
# apt-get update
# apt-get install apache2
# apt-get install mariadb-server
# apt-get install libapache2-mod-php
# apt-get install php
# apt-get install php-mysql
```

### 4. Prepare PHP

Make sure that PHP is setup correctly. In particular, enable the `short_open_tag` for the `apache` version of PHP. On Debian, this is currently stored in `/etc/php/7.3/apache/php.ini` (but will change as PHP continues to upgrade: just look in `/etc/php` to get the right directory):
```
short_open_tag = On
```
If you had to change this, restart `apache`:
```
# /etc/init.d/apache2 restart
```

### 5. Configure Apache

Create the Apache host file for your login/UserDB host by creating a file like the following in `/etc/apache2/sites-available/login.conf`:
```
<VirtualHost *>

	ServerName login.yourgame.com
	ServerAdmin webmaster@localhost
	DocumentRoot /var/www/html/user
	<Directory /var/www/html/user/>
		Options FollowSymLinks
		AllowOverride None
		Require all granted
	</Directory>
	
	ErrorLog ${APACHE_LOG_DIR}/login-error.log
	CustomLog ${APACHE_LOG_DIR}/login-access.log combined

</VirtualHost>
```
Depending on your setup, you may also need to edit `/etc/apache2/ports.conf` to include support for port 443, in preperation for SSL.
```
Listen YOUR_IP:80

<IfModule ssl_module>
	Listen YOUR_IP:443
</IfModule>

<IfModule mod_gnutls.c>
	Listen YOUR_IP:443
</IfModule>
```
You then need to enable the site and restart `apache` (again):
```
# a2ensite login.conf
# systemctl reload apache2
```

#### 5A. Turn SSL on for Apache

Install the Let's Encrypt Certbot:
```
# apt-get install certbot
# apt-get install python-certbot-apache
```
Now, run `certbot` and on the Q&A tell it to (1) set up a certificate for your login domain and (2) install redirects from HTTP to HTTPS.

### 6. Install MariaDB Tables & Users

#### 6A. Create the UserDB Database

Create the `userdb` database:
```
# mysql -p
MariaDB [(none)]> create database userdb;
Query OK, 1 row affected (0.00 sec)
MariaDB [(none)]> quit
```
Technically, you can call this by a different name if you want, just adjust future commands and then `config/database.json` appropriately.

#### 6B. Fill the UserDB Tables

Fill the `userdb` with standard tables:
```
# cat /var/www/html/user/database/userdb-schema.mysql | mysql -p userdb
```

#### 6B.1 ALTERNATIVE: Copy a UserDB Table

If you are instead moving your database from a different machine, you must dump it on the remote machine:
```
remote# mysqldump -p userdb > /tmp/userdb.mysql
```
Then can them import it on the local machine:
```
local# mysql -p userdb < /tmp/userdb.mysql 
```

#### 6C. Set Up the UserDB User

Set up a user with access to that new database:
```
# mysql -p
MariaDB [(none)]> create user 'userdb'@'localhost' IDENTIFIED BY 'your-pass';
Query OK, 0 rows affected (0.00 sec)

MariaDB [(none)]> grant all privileges on userdb.* TO 'userdb'@'localhost';
Query OK, 0 rows affected (0.00 sec)

MariaDB [(none)]> flush privileges;
Query OK, 0 rows affected (0.00 sec)

```
### 7. Double-Check Your Config Files

Before your start, double-check your config files and make sure they're all set up appropriately. You can also consult the `assets` directory to adjust the TOS, the background image, the graphic logo, and the CSS.

### 8. Firewall Your System

Make sure your UserDB ports (by default localhost:9970 and localhost:9971) are protected by a firewall. In the default setup, do not allow anyone access to the ports except localhost (which is the default setup for most firewalls). If you are running the UserDB server on a different machine, make sure that the web server and the game server both have access to these machines.

_The UserDB is meant to be secured by a firewall. There are Control functions that do not require authentication, and if you do not install a firewall, anyone can use them!_

### 9. Automate the Servers

Set the Auth and Ctl UserDB servers to automatically run (and rerun) by installing the following into your crontab:
```
* * * * * /var/www/html/user/admin/restartuserdb.sh
```
This will check the servers every minute, and restart any that isn't running.

(Note that this means that if you ever want to make a change to your server services ... or if you ever download a new copy from GitHub, all you have to do is kill the `server-auth.php` and `server-control.php` processes, and they'll restart with the new code within a minute.)

If you plan to give out monthly StoryPoints for premium accounts, as was done on classic Skotos games, you should also put something like the following in your cron:
```
1 4 1-3 * * /var/www/html/user/admin/grantsps.sh
```
This will attempt a StoryPoint disbursement on each of the first three days of the month. Note that the `grantsps` script is set to only actually give those points out once a month, so the extra runs just ensure that SPs are lost due to server downtime.

### 10. Tell Your Game to Connect to the New Server

For a SkotOS game, you should first edit `/usr/System/data/userdb` under `/var/skotos/X000/skoot` in your SkotOS setup. It should be set to your local IP and port:
```
userdb-hostname 127.0.0.1
userdb-portbase 9900
```
(Please note that the `/usr/System/data` directory also contains the `instance` file where you'd change things like `hostname` and `portbase` for your game, if need be.)

Next, adjust the userdb-authctl glue script, usually located at `/var/skotos/support/misc/userdb-authctl`. 

There should be two lines that need to be set to `localhost`:
```
	$remote = IO::Socket::INET->new(PeerAddr  => "localhost:9970",
```
And:
```
	$remote = IO::Socket::INET->new(PeerAddr  => "localhost:9971",
```
(Technically, both of these adjustments aren't required, but it's best to be thorough, future-proofing your setup.)

You will need to restart `userdb-authctl` afterward, using a command like `nohup /usr/bin/perl /var/skotos/support/misc/userdb-authctl 8000 &`, adjusted correctly for your game instance and the location of your files.

### 10A. Tell Your Game to Point to the New Login Page

This can be found in `/usr/HTTP/sys/httpd.c` in your game instance (`/var/skotos/X000/skoot`).

Adjust it to something like the following:
```
   case LOCAL_USERDB:
      return "https://login.lovecraft.skotos.net/login.php";
```
Afterward you will need to log in to the admin port and recompile this file:
```
# telnet lovecraft 3098
Trying 23.239.4.104...
Connected to lovecraft.skotos.net.
Escape character is '^]'.

Welcome to SkotOS.

...
> compile /usr/HTTP/sys/httpd.c
$18 = </usr/HTTP/sys/httpd>
```

Your UserDB server should now be operating and ready to start accepting users for your game.

### 11. Set an Administrative Users

If you started with the standard UserDB tables in step 6B, you will now need to bootstrap yourself an administrative user.

First, create a user using your web interface.

Then, go to MySQL and give that user 'access' for your gameID as set in your `general.json` config file.
```
# mysql
> use userdb;
> INSERT INTO access (ID, game) values (1, 'gameid');
```
(This example would give UID #1 login-admin permissions for the gameID `gameid`; that should match the `access` line in your `instance` file).

Afterward, you can then use the `support.php` page for other administrative work.

### 12. Administering Your System

Some additional work will be needed long-term to keep your system up to date.

#### 12A. Updating Your Server

If you ever want to retrieve the newest version of the thin-auth server code from GitHub, you can do so like this:
```
# cd /var/www/html/user
# git pull
```
Obviously, you will need to restart the servers aftewrard; this can be done by killing the `server-auth.php` and `server-client.php` processes. They will restart from the crontab on the next minute.

#### 12B. Maintaining Your Certs

You will need to renew your SSL/TLS certificates on a regular basis. Insert something like the following into your `crontab` to do so:
```
1 5 1-2	* * /usr/bin/certbot renew
```
This example will try and renew your certs on the 1st and 2nd of each month (with the extra day just in case your machine is down on th eother).

#### 12C. Rotating Your Logs

You will want to rotate your `/var/log/userdb.log` and (if you're using it) `/var/log/tunnel`. This requires `logrotate`, which will typically be installed on most systems already. If so, you can simply add an `/etc/logrotate.d/skotos` file, as follows:
```
/var/log/userdb.log {
		    weekly
		    rotate 8
		    size 1M
}

/var/log/tunnel/* {
		  weekly
		  rotate 3
		  compress
		  delaycompress
		  size 1M
}		  
```
This will rotate the UserDB and tunnel files on a weekly basis, retaining 8 logs for the UserDB and 3 for the tunnel.
