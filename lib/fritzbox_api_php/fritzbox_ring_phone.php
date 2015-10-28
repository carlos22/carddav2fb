<?php
$dial = '**610'; // set to the number to dial
// init the output message
$message = date('Y-m-d H:i') . ' ';
try
{ 
  // load the fritzbox_api class
  require_once(__DIR__ . '/fritzbox_api.class.php');
  $fritz = new fritzbox_api();
  
  // update the setting
  $formfields = array(
    'telcfg:command/Dial'      => $dial,
  );
  $fritz->doPostForm($formfields);
  $message .= 'Phone ' . $dial . ' ringed.';
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