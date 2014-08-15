### Important! ###
Do not use Windows Notepad as it will break the files due to their UTF-8 coding and UNIX linebreaks. Download a serious editor like Notepad++ instead.
Also do not use or modify this scripts, if you do not know, what you do. This script is itended for advanced users


### Upgrade advisory for version 0.5.0 and up ####

Version 0.5.0 introduced a new config format with graceful default values, so check the new config file fritzbox_user.conf.php


### Basic usage advisory ####

The scripts are well documented, please take a look to understand them.
This is also the right moment to check the config file "fritzbox.conf.php"!

All files must be in the same directory. If this condition is fulfilled, you don't need to pay any more attention to "fritzbox_api.class.php".

To use "fritzbox_tam_on_off.php", you have to call it in a terminal, it will then explain its usage. This script is a reference implementation of the PHP Fritz!Box API, use it as a base for your own implementations



### Usage on a Windows system ###

The script works fine on my Windows 7 machine and a simply extraced PHP 5.3 distribution (get it at http://windows.php.net/download/, PHP 5.4 works as well). The cURL extension and the mbstring extenstion must be enabled in the php.ini and the correct timezone must be set. To do this, copy the php.ini-production to php.ini and uncomment/change the following directives:

extension_dir = "ext"
extension=php_curl.dll
extension=php_mbstring.dll
date.timezone = Europe/Berlin

After you have done that, you can call the script in a terminal:

  C:\>"path\to\php.exe" "path\to\fritzbox_tam_on_off.php"
  
i.e. if you extracted PHP to c:\php and the Fritz!Box PHP API to a subfolder named fritz_api, you call it this way:
  C:\>cd php
  C:\php>"php.exe" "fritz_api\fritzbox_tam_on_off.php"
  
If you use the Windows taskplanner, configure the logger to a logfile or to silent mode and call via php-win.exe instead of php.exe. This will prevent the terminal from being opened.

  

### Usage on a UNIX system ###

If you use UNIX, you should be familiar with calling a PHP script via the PHP-CLI. If you don't have the PHP-CLI installed, you can edit the $argv part in "fritz_tam_on_off.php" to work with PHP-CGI and $_GET or you hardcode the necessary arguments.

Ensure that the cURL and the mbstring extensions are available and a valid timezone is set. The script was tested on a Ubuntu Server machine with PHP 5.3.



### Feedback, license, support ###
You are welcome to send me feedback via email (Gregor [at] der-meyer.de) or visit my personal blog at http://spackmat.de/spackblog (in German)

The whole work is licensed under a Creative Commons cc-by-sa license (http://creativecommons.org/licenses/by-sa/3.0/de/). You are free to use and modify it, even for commercial use. If you redistribute it, you have to ensure my name is kept in the code and you use the same conditions.

Feel free to add (and contribute) other nice scripts. The API class should work with any config item, which is available in the Fritz!Box Web UI. Use Firebug or LiveHeaders to figure out the name of the proper POST-Field.

I won't give any personal support for this script, unless you pay for it. :) I'm a freelance IT-consultant and webdeveloper, loocated in Düsseldorf, Germany.