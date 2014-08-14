<?php
/**
 * Fritz!Box PHP tools CLI script to swap answering machines on given numbers
 *
 * Must be called via a command line, shows a help message if called without any or an invalid argument
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
  
  // update the setting
  $formfields = array(
    'getpage'                     => '../html/de/menus/menu2.html',
    'tam:settings/TAM0/Active'    => '1',
    'tam:settings/TAM1/Active'    => '1',
    'tam:settings/MSN0'           => '0211 123456',
    'tam:settings/MSN1'           => '0211 123457',
    'tam:settings/MSN1'           => '0211 123458', // and so on for all configured MSNs
    'tam:settings/TAM0/MSNBitmap' => '1',
    'tam:settings/TAM1/MSNBitmap' => '2',
  );
  // the MSNBitmap is a decimal representation of a bitmap of the MSNs above
  // 0 stands for all MSNs, 1 for only the first MSN, 2 for the second, 4 for the third,
  // 3 for the first and the second, 5 for the first and the third and so on
  // this is based on 1 => 1, 2 => 10, 3 => 11, 4 => 100, 5 => 101, 6 => 110, 7 => 111
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