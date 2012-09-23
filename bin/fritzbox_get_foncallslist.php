<?php
/**
 * Fritz!Box PHP tools CLI script to download the calllist from the Box
 *
 * Must be called via a command line, shows a help message if called without any or an invalid argument
 * Can log to the console or a logfile or be silent
 *
 * Check the config file fritzbox.conf.php!
 * 
 * @author   Gregor Nathanael Meyer <Gregor [at] der-meyer.de>
 * @license  http://creativecommons.org/licenses/by-sa/3.0/de/ Creative Commons cc-by-sa
 * @version  0.1
 * @package  Fritz!Box PHP tools
 */

// load the config and the API class
require_once(dirname(__FILE__) . '/fritzbox.conf.php');
require_once(dirname(__FILE__) . '../lib/fritzbox_api.class.php');

function log_message($message)
{
  global $logging;
  global $newline;
  
  if (!isset($logging) || (isset($logging) && $logging == 'console'))
  {
    echo $message;
  }
  else if (isset($logging) && $logging == 'silent')
  {
    // do nothing
  }
  else
  {
    file_put_contents($logging, $message . $newline, FILE_APPEND);
  }
}

// init the output message
$message = date('Y-m-d H:i') . ' ';


// handle the fritzbox_api class and do the job
try
{
  if ( isset($enable_remote_config) && isset($remote_config_user) && isset($remote_config_password) )
  {
    $fritz = new fritzbox_api($password, $fritzbox_ip, true, $remote_config_user, $remote_config_password);
  }
  else
  {
    $fritz = new fritzbox_api($password, $fritzbox_ip);
  }
  
  // get the frontend-page to refresh the list
  $params = array(
    'getpage'             => '../html/de/menus/menu2.html',
    'var:menu'                => 'fon',
    'var:pagename'        => 'foncalls',
    'var:errorpagename'   => 'foncalls',
    'var:type'            => '0',
    //'telcfg:settings/UseJournal'  => '1',
  );
  $fritz->doPostForm($params);
  
  // get the phone calls list
  $params = array(
    'getpage'         => '../html/de/home/foncallsdaten.xml',
    //'getpage'         => '../html/de/FRITZ!Box_Anrufliste.csv',
  );
  $output = $fritz->doGetRequest($params);
  
  // write out the call list to the desired path
  file_put_contents($foncallslist_path, $output);
  
  // set a log message
  $message .= 'Call list sucessfully downloaded';
  
  // destroy the object to log out
  $fritz = null;
}
catch (Exception $e)
{
  $message .= $e->getMessage();
}

// log the result
log_message($message);
?>
