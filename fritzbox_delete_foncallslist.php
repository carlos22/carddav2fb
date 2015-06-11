<?php
/**
 * Fritz!Box PHP tools CLI script to delete the calllist from the Box
 *
 * v0.1: Initial 
 * 
 * @author   Benjamin Rehn <Benjamin.Rehn  [at] gmail.com>
 * @license  http://creativecommons.org/licenses/by-sa/3.0/de/ Creative Commons cc-by-sa
 * @version  0.1 2015-06-11
 * @package  Fritz!Box PHP tools
 */

try
{
  // load the fritzbox_api class
  require_once('fritzbox_api.class.php');
  $fritz = new fritzbox_api();
  
  // init the output message
  $message = date('Y-m-d H:i') . ' ';
  
  // delete calllist form fields
  $formfields = array(
	'getpage'	=> '/fon_num/foncalls_list.lua',
	'usejournal' 	=> '1',
	'callstab' 	=> 'all',
	'submit'	=> 'clear',
	'clear'	=> '1',
  );
  
  $fritz->doPostForm($formfields);
  // set a log message
  $message .= 'Call list sucessfully deleted';
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
