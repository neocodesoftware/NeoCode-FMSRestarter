 _    _      _      _________  ___ _____    ___      _           _        ______          _             _
| |  | |    | |     |  ___|  \/  |/  ___|  / _ \    | |         (_)       | ___ \        | |           | |
| |  | | ___| |__   | |_  | .  . |\ `--.  / /_\ \ __| |_ __ ___  _ _ __   | |_/ /___  ___| |_ __ _ _ __| |_ ___ _ __
| |/\| |/ _ \ '_ \  |  _| | |\/| | `--. \ |  _  |/ _` | '_ ` _ \| | '_ \  |    // _ \/ __| __/ _` | '__| __/ _ \ '__|
\  /\  /  __/ |_) | | |   | |  | |/\__/ / | | | | (_| | | | | | | | | | | | |\ \  __/\__ \ || (_| | |  | ||  __/ |
 \/  \/ \___|_.__/  \_|   \_|  |_/\____/  \_| |_/\__,_|_| |_| |_|_|_| |_| \_| \_\___||___/\__\__,_|_|   \__\___|_|

# web-far
Web FMS Admin Restarter. FileMaker Server restarting web tool
 
-- Requirements ==
    PHP
    Web Server
    FileMaker admin console

== Installation ==

Download fmsrestarter.zip and unzip it in the root folder of your Web server.
Configure your web server, here is an example for apache:
<VirtualHost fmrestarter>
    DocumentRoot "d:/fmrestarter"
    ServerName restarter
    ErrorLog "logs/fmrestarter-error.log"
    CustomLog "logs/fmrestarter-access.log" common
    Options -Indexes -FollowSymLinks -Includes -ExecCGI

	<Directory d:/fmrestarter>
		Order allow,deny
		Deny from all
		<Files index.php>
			Order Allow,Deny
			Allow from all
		</Files>
	</Directory>
    <Directory d:/fmrestarter>
    	Order Allow,Deny
		Allow from all
	</Directory>
</VirtualHost>

* Make sure the log and stat directories have read/write permissions for the web server user


== Configuration ==

1. Create MD5 checksum of your FM Server admin console login/password
Access index.php?act=checksum  with a webbrowser and enter your FM Server admin console login/password to generate the checksum
Save checksum value as MD5check value in restart.ini file
Using this checksum we check FileMaker server admin console login/password on web server's side and don't send invalid login/pass to FileMaker
MD5check = dfc8dc2b9e0e7d93b9146d812367f035

2. Configure FM Server access

2.1 If you run your FileMaker server on the same server set
fms_server = LOCAL

2.2 If you run your FileMaker server on the distant server configure the following parametrs:
Server ssh host:port:
fms_server = "192.168.1.107"

2.2.1. FileMaker server ssh username and path to RSA key for password-less authentication:
fms_user = admin
; Path to RSA key for key authorization on FileMaker server
fms_public_key_path = id_rsa_win

2.2.2. Alternatively you can connect with ssh login/pass instead of RSA key (not recommended)
ssh_login = admin
ssh_password = YOUR_PASSWORD_HERE

3. Configure FM local server path to fmsadmin

Example for MAC:
; Path to admin console
fmsadmin_path = "/Library/FileMaker\ Server/Database\ Server/bin/fmsadmin"
; Script to restart FileMaker
; PARAMETERS will be replaced with all required parameters
; Unix/Max version with " > /dev/null 2>&1 &" to redirect all output to null and don't wait for any output
exec_cmd = 'php restart.php PARAMETERS > /dev/null 2>&1 &'

Example for Windows:
; Path to admin console
 fmsadmin_path = "DOUBLEQUOTEC:\Program Files\FileMaker\FileMaker Server\Database Server\fmsadminDOUBLEQUOTE"
; Script to restart FileMaker
; PARAMETERS will be replaced with all required parameters
; Windows version with "start /b " to start an application without opening a new Command Prompt window.
 exec_cmd = "start /b php restart.php PARAMETERS"

== Run It ==
Now you can open index.php, enter your FileMaker admin console login/password and restart server or disconnect clients.
