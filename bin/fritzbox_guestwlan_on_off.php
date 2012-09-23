<?php
/**
 * Fritz!Box PHP tools CLI script to enable or disable the WLAN guest access
 *
 * Must be called via a command line, shows a help message if called without any or an invalid argument
 * Can log to the console or a logfile or be silent
 *
 * Check the config file fritzbox.conf.php!
 * 
 * @author   Gregor Nathanael Meyer <Gregor [at] der-meyer.de>
 * @license  http://creativecommons.org/licenses/by-sa/3.0/de/ Creative Commons cc-by-sa
 * @version  0.1 2011-06-15
 * @package  Fritz!Box PHP tools
 */

// load the config
require_once('fritzbox.conf.php');
 
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

// handle the CLI arguments or give a help message
if (isset($argv[1]) && ($argv[1] == 0 || $argv[1] == 1) )
{
  $mode = (bool)$argv[1];
}
else
{
  if (!isset($logging) || (isset($logging) && $logging == 'console'))
  {
    echo '
    Enables or disables the WLAN guest access of a Fritz!Box
    
    Usage on UNIX systems:
      /path/to/php ' .  $argv[0] . ' {0|1} [optional: PASSWORD]
    
    Usage on Windows systems:
      c:\path\to\php.exe ' .  $argv[0] . ' {0|1} [optional: PASSWORD]
    
    0 disables the guest access
    1 enables the guest access
    
    The optional argument PASSWORD sets a new guest access password (min 8 chars)
    Defaults to false, so the current password is kept.
    ';
  }
  else if (isset($logging) && $logging == 'silent')
  {
    // do nothing
  }
  else
  {
    log_message($message . 'ERROR: Script was called without or with an invalid argument');
  }
  exit;
}
$wpa_key = (isset($argv[2]) && strlen($argv[2]) >= 8 && strlen($argv[2]) <= 63) ? $argv[2] : false;


// handle the fritzbox_api class and do the job
require_once('../lib/fritzbox_api.class.php');
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
  
  // read the current settings
  $formfields = array(
    'getpage' => '/wlan/guest_access.lua',
  );
  $output = $fritz->doGetRequest($formfields);
  
  // read down_time_activ setting
  preg_match('@name="down_time_activ"[^>]+(checked)[^>]*@', $output, $matches);
  if ( isset($matches[1]) )
  {
    $formfields['down_time_activ'] = 'on';
  }
  // read down_time_value setting
  preg_match('@name="down_time_value".*?<option value="(\d+)"[^>]+?selected.*?</select>@s', $output, $matches);
  $formfields['down_time_value'] = isset($matches[1]) ? $matches[1] : '15';
  // read disconnect_guest_access setting
  preg_match('@name="disconnect_guest_access"[^>]+(checked)[^>]*@', $output, $matches);
  if ( isset($matches[1]) )
  {
    $formfields['disconnect_guest_access'] = 'on';
  }
  // read guest_ssid setting
  preg_match('@name="guest_ssid"[^>]+value="([^"]*)"[^>]*@', $output, $matches);
  $formfields['guest_ssid'] = isset($matches[1]) ? $matches[1] : 'defaultguestaccess';
  // read wlan_security setting
  preg_match('@name="wlan_security"[^>]+value="([^"])"[^>]+checked[^>]*@', $output, $matches);
  $formfields['wlan_security'] = isset($matches[1]) ? $matches[1] : '15';
  // read wpa_key setting
  preg_match('@name="wpa_key"[^>]+value="([^"]*)"[^>]*@', $output, $matches);
  $formfields['wpa_key'] = isset($matches[1]) ? $matches[1] : 'defaultwpakey';
  // read wpa_modus setting
  preg_match('@name="wpa_modus".*?<option value="(\d+)"[^>]+?selected.*?</select>@s', $output, $matches);
  $formfields['wpa_modus'] = isset($matches[1]) ? $matches[1] : 'x';
  
  // set new given setting
  if ( $mode == true )
  {
    $formfields['activate_guest_access'] = 'on';
    if ( $wpa_key !== false )
    {
      $formfields['wpa_key'] = $wpa_key;
    }
  }
  
  // do the update
  $formfields['btnSave'] = '';
  $output = $fritz->doPostForm($formfields);

  preg_match('@name="activate_guest_access"[^>]+(checked)[^>]*@', $output, $matches);
  if ( isset($matches[1]) && $mode == true )
  {
		preg_match('@name="wpa_key"[^>]+value="([^"]*)"[^>]*@', $output, $matches);
		if ( isset($matches[1]) )
		{
			$message .= 'WLAN guest access is now active. WPA-Key is "' . $matches[1] . '"';
		}
  }
  else if ( !isset($matches[1]) && $mode == false )
  {
    $message .= 'WLAN guest access is now inactive.';
  }
  else if ( isset($matches[1]) && $mode == false )
  {
    $message .= 'ERROR: WLAN guest access status change failed, should be inactive, but is still active.';
  }
  else if ( !isset($matches[1]) && $mode == true )
  {
    $message .= 'ERROR: WLAN guest access status change failed, should be active, but is still inactive.';
  }
  $fritz = null; // destroy the object to log out
}
catch (Exception $e)
{
  $message .= $e->getMessage();
}

// log the result
log_message($message);
?>
