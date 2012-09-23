<?php
// load the config
require_once('fritzbox.conf.php');
 
// load the fritzbox_api class
require_once('../lib/fritzbox_api.class.php');

$dial = '**610'; // set to the number to dial

// do the job
try
{
  $fritz = new fritzbox_api($password, $fritzbox_ip);
  
  // update the setting
  $formfields = array(
    'getpage'                             => '../html/de/menus/menu2.html',
    'telcfg:command/Dial'                 => $dial,
  );
  $fritz->doPostForm($formfields);
  $fritz = null; // destroy the object to log out
}
catch (Exception $e)
{
  echo $e->getMessage();
}
?>
