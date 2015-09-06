<?php
if ( !isset($this->config) )
{
  die(__FILE__ . ' must not be called directly');
}

####################### central API config ########################
# notice: you only have to set values differing from the defaults #
###################################################################

# use the new .lua login method in current (end 2012) labor and newer firmwares (Fritz!OS 5.50 and up)
$this->config->setItem('use_lua_login_method', true);

# set to your Fritz!Box IP address or DNS name (defaults to fritz.box), for remote config mode, use the dyndns-name like example.dyndns.org
$this->config->setItem('fritzbox_ip', 'fritz.box');

# if needed, enable remote config here
#$this->config->setItem('enable_remote_config', true);
#$this->config->setItem('remote_config_user', 'test');
#$this->config->setItem('remote_config_password', 'test123');

# set to your Fritz!Box username, if login with username is enabled (will be ignored, when remote config is enabled)
$this->config->setItem('username', false);

# set to your Fritz!Box password (defaults to no password, will be ignored, when remote config is enabled)
$this->config->setItem('password', false);

# set the logging mechanism (defaults to console logging)
$this->config->setItem('logging', 'console'); // output to the console
#$this->config->setItem('logging', 'silent');  // do not output anything, be careful with this logging mode
#$this->config->setItem('logging', 'tam.log'); // the path to a writeable logfile

# the newline character for the logfile (does not need to be changed in most cases)
$this->config->setItem('newline', (PHP_OS == 'WINNT') ? "\r\n" : "\n");


############## module specific config ###############

# set the path for the call list for the foncalls module
$this->config->setItem('foncallslist_path', __DIR__ . '\foncallsdaten.csv');