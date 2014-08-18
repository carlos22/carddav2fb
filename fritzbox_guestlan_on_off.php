<?php
/**
 * Fritz!Box PHP tools CLI script to enable or disable the LAN4 guest access
 *
 * Must be called via a command line, shows a help message if called without any or an invalid argument
 * Can log to the console or a logfile or be silent
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

  // handle the CLI arguments or give a help message
  if (isset($argv[1]) && ($argv[1] == 0 || $argv[1] == 1) )
  {
    $mode = (bool)$argv[1];
  }
  else
  {
    if ( $fritz->config->getItem('logging') == 'console' )
    {
      echo '
  Enables or disables the LAN4 guest access of a Fritz!Box
  
  Usage on UNIX systems:
    /path/to/php ' .  $argv[0] . ' {0|1}
  
  Usage on Windows systems:
    "c:\path\to\php.exe" ' .  $argv[0] . ' {0|1}
  
  0 disables the guest access
  1 enables the guest access
  ';
    }
    else
    {
      $fritz->logMessage($message . 'ERROR: Script was called without or with an invalid argument');
    }
    exit;
  }
  
  
  // read the current settings
  $formfields = array(
    'getpage' => '/net/network_settings.lua',
  );
  $output = $fritz->doGetRequest($formfields);
  
  
  // read time_server_activ setting
  preg_match('@name="time_server_activ"[^>]+(checked)[^>]*@', $output, $matches);
  if ( isset($matches[1]) )
  {
    $formfields['time_server_activ'] = 'on';
  }
  // read time_server setting
  preg_match('@name="time_server"[^>]+value="([^"]*)"[^>]*@', $output, $matches);
  $formfields['time_server'] = isset($matches[1]) ? $matches[1] : '0.europe.pool.ntp.org';
  // read other_prefix_allowed setting
  preg_match('@name="other_prefix_allowed"[^>]+(checked)[^>]*@', $output, $matches);
  if ( isset($matches[1]) )
  {
    $formfields['other_prefix_allowed'] = 'on';
  }
  // read dnsv6_server_activ setting
  preg_match('@name="dnsv6_server_activ"[^>]+(checked)[^>]*@', $output, $matches);
  if ( isset($matches[1]) )
  {
    $formfields['dnsv6_server_activ'] = 'on';
  }
  
  // set new given setting
  if ( $mode == true )
  {
    $formfields['guest_enabled'] = 'on';
  }
  
  // do the update
  $formfields['btnSave'] = '';
  $output = $fritz->doPostForm($formfields);

  preg_match('@name="guest_enabled"[^>]+(checked)[^>]*@', $output, $matches);
  if ( isset($matches[1]) && $mode == true )
  {
		$message .= 'LAN4 guest access is now active.';
  }
  else if ( !isset($matches[1]) && $mode == false )
  {
    $message .= 'LAN4 guest access is now inactive.';
  }
  else if ( isset($matches[1]) && $mode == false )
  {
    $message .= 'ERROR: LAN4 guest access status change failed, should be inactive, but is still active.';
  }
  else if ( !isset($matches[1]) && $mode == true )
  {
    $message .= 'ERROR: LAN4 guest access status change failed, should be active, but is still inactive.';
  }
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