<?php
$diversity = 0; // set to the interlan ID of the call diversion, the first one has ID 0;
$mode = 1; // set the mode to 0 or 1 do disable or enable the call diversion


try
{
  // load the fritzbox_api class
  require_once('fritzbox_api.class.php');
  $fritz = new fritzbox_api();
  
  // init the output message
  $message = date('Y-m-d H:i') . ' ';

  // update the setting
  $formfields = array(
    'getpage'                             => '../html/de/menus/menu2.html',
    'telcfg:settings/Diversity' . $diversity . '/Active' => $mode,
  );
  $fritz->doPostForm($formfields);
}
catch (Exception $e)
{
  $message .= $e->getMessage();
}

// log the result
if ( isset($fritz) && is_object($fritz) && get_class($fritz) == 'fritzbox_api' )
{
  $fritz->logMessage($message);
}
else
{
  echo($message);
}
$fritz = null; // destroy the object to log out
?>