<?php
// load the config
require_once('fritzbox.conf.php');
 
// load the fritzbox_api class
require_once('../lib/fritzbox_api.class.php');

$diversity = 0; // set to the interlan ID of the call diversion, the first one has ID 0;
$mode = 1; // set the mode to 0 or 1 do disable or enable the call diversion

// do the job
try
{
  $fritz = new fritzbox_api($password, $fritzbox_ip);
  
  // update the setting
  $formfields = array(
    'getpage'                             => '../html/de/menus/menu2.html',
    'telcfg:settings/Diversity' . $diversity . '/Active' => $mode,
  );
  $fritz->doPostForm($formfields);
  $fritz = null; // destroy the object to log out
}
catch (Exception $e)
{
  echo $e->getMessage();
}
?>
