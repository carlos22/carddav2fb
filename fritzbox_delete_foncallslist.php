<?php
/**
 * Fritz!Box PHP tools CLI script to download the calllist from the Box
 *
 * Must be called via a command line, shows a help message if called without any or an invalid argument
 * v0.3: Changed to download the new csv-file instead of the old xml, which is empty on newer firmwares
 * on older firmwares use fritzbox_get_foncallslist_xml.php instead
 *
 * Check the config file fritzbox.conf.php!
 * 
 * @author   Gregor Nathanael Meyer <Gregor [at] der-meyer.de>
 * @author   Benjamin Rehn <Benjamin.Rehn  [at] gmail.com>
 * @license  http://creativecommons.org/licenses/by-sa/3.0/de/ Creative Commons cc-by-sa
 * @version  0.4 2013-01-02
 * @package  Fritz!Box PHP tools
 */

try
{
  // load the fritzbox_api class
  require_once('fritzbox_api.class.php');
  $fritz = new fritzbox_api();
  
  // init the output message
  $message = date('Y-m-d H:i') . ' ';
  
  // DELETE LIST AFTER DOWNLOAD
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
