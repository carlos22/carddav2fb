<?php
/**
 * Fritz!Box PHP tools CLI script to download the calllist from the Box
 *
 * Must be called via a command line, shows a help message if called without any or an invalid argument
 * works only with older firmwares, since AVM changed the file format, on newer firmwares restults in an empty file
 * use new fritzbox_get_foncallslist.php instead
 *
 * Check the config file fritzbox.conf.php!
 * 
 * @author   Gregor Nathanael Meyer <Gregor [at] der-meyer.de>
 * @license  http://creativecommons.org/licenses/by-sa/3.0/de/ Creative Commons cc-by-sa
 * @version  0.3 2013-01-02
 * @package  Fritz!Box PHP tools
 */

try
{
  // load the fritzbox_api class
  require_once('fritzbox_api.class.php');
  $fritz = new fritzbox_api();
  
  // init the output message
  $message = date('Y-m-d H:i') . ' ';
  
  if ( !$fritz->config->getItem('foncallslist_path') )
  {
    throw new Exception('Mandatory config Item foncallslist_path not set.');
  }
  if ( ( file_exists($fritz->config->getItem('foncallslist_path')) && !is_writable($fritz->config->getItem('foncallslist_path')) ) || ( !file_exists($fritz->config->getItem('foncallslist_path')) && !is_writable(dirname($fritz->config->getItem('foncallslist_path'))) ) )
  {
    throw new Exception('Config item foncallslist_path (' . $fritz->config->getItem('foncallslist_path') . ') is not writeable.');
  }

  // get the frontend-page to refresh the list
  $params = array(
    'getpage'             => '../html/de/menus/menu2.html',
    'var:menu'                => 'fon',
    'var:pagename'        => 'foncalls',
    'var:errorpagename'   => 'foncalls',
    'var:type'            => '0',
  );
  $fritz->doPostForm($params);
  
  // get the phone calls list
  $params = array(
    'getpage'         => '../html/de/home/foncallsdaten.xml',
  );
  $output = $fritz->doGetRequest($params);
  
  // write out the call list to the desired path
  file_put_contents($fritz->config->getItem('foncallslist_path'), $output);
  
  // set a log message
  $message .= 'Call list sucessfully downloaded';
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