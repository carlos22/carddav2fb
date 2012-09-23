<?php
// load the config
require_once('fritzbox.conf.php');
 
// load the fritzbox_api class
require_once('../lib/fritzbox_api.class.php');

// do the job
try
{
  $fritz = new fritzbox_api($password, $fritzbox_ip);
  $formfields = array(
    'getpage' => '/system/reboot.lua',
  );
  $output = $fritz->doGetRequest($formfields);
  $formfields['reboot'] = '';
  $output = $fritz->doPostForm($formfields);
  $fritz = null; // destroy the object to log out
}
catch (Exception $e)
{
  echo $e->getMessage();
}
?>
