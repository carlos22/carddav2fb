<?php
#####################################################
#################### the config #####################
#####################################################

# set to your Fritz!Box IP address or DNS name (defaults to fritz.box), for remote config mode, use the dyndns-name like example.dyndns.org
$fritzbox_ip = 'fritz.box';

# if needed, enable remote config here
#$enable_remote_config   = true;
#$remote_config_user     = 'test';
#$remote_config_password = 'test123';

# set to your Fritz!Box password (defaults to no password)
$password    = false;

# set the logging mechanism (defaults to console logging)
$logging     = 'console'; // output to the console
#$logging     = 'silent';  // do not output anything, be careful with this logging mode
#$logging     = 'tam.log'; // the path to a writeable logfile

# the newline character for the logfile (does not need to be changed in most cases) [note should be a constant!]
$newline = PHP_EOL;
// for pre php 5.0.2 use this:
//$newline = (PHP_OS == 'WINNT') ? "\r\n" : "\n";



############## module specific config ###############

# set the path for the call list for the foncalls module
$foncallslist_path = dirname(__FILE__) . '/foncallsdaten.xml';
$fonbook_import_path = dirname(__FILE__) . '/example_pb.xml';
$fonbook_export_path = dirname(__FILE__) . '/exported_pb.xml';
