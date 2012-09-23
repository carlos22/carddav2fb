<?php
/**
 * Fritz!Box PHP tools CLI script to enable or disable answering machines
 *
 * Must be called via a command line, shows a help message if called without any or an invalid argument
 * Can log to the console or a logfile or be silent
 * new in v0.2: Can handle remote config mode via https://example.dyndns.org
 *
 * Check the config file fritzbox.conf.php!
 * 
 * @author   Gregor Nathanael Meyer <Gregor [at] der-meyer.de>
 * @license  http://creativecommons.org/licenses/by-sa/3.0/de/ Creative Commons cc-by-sa
 * @version  0.2
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
  $mode = (int)$argv[1];
}
else
{
  if (!isset($logging) || (isset($logging) && $logging == 'console'))
  {
    echo '
    Enables or disables an answering machine (TAM) of a Fritz!Box
    
    Usage on UNIX systems:
      /path/to/php ' .  $argv[0] . ' {0|1} [optional: TAM]
    
    Usage on Windows systems:
      c:\path\to\php.exe ' .  $argv[0] . ' {0|1} [optional: TAM]
    
    0 disable the TAM
    1 enable the TAM
    
    The optional argument TAM and addresses the nth answering machine
    Defaults to 0, which is the TAM **600; 1 is the TAM **601 and so on
    
    Warning: A non existent TAM will not lead to an error message but adds a
    new TAM to the Fritz!Box, which will answer all incoming calls by default!
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
$tam = (isset($argv[2]) && $argv[2] >= 0 && $argv[2] <= 9) ? (int)$argv[2] : 0;


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
  
  // update the setting
  $formfields = array(
    'getpage'                             => '../html/de/menus/menu2.html',
    'tam:settings/TAM' . $tam . '/Active' => $mode,
  );
  $fritz->doPostForm($formfields);
  
  // check if the update succeded
  $formfields = array(
    'getpage'         => '../html/de/menus/menu2.html',
    'var:menu'        => 'home',
    'var:pagemaster'  => 'fondevices',
    'var:pagename'    => 'fondevices',
  );
  $output = $fritz->doPostForm($formfields);
  
  preg_match('@name="tam:settings/TAM' . $tam . '/Active" value="([10])"@i', $output, $matches);
  if (isset($matches[1]) && $matches[1] == $mode)
  {
    $message .= $mode == 1 ? 'TAM' . $tam . ' enabled' : 'TAM' . $tam . ' disabled';
  }
  else if (isset($matches[1]))
  {
    $message .= 'ERROR: TAM' . $tam . ' status change failed, should be ' . $mode . ', but is ' . $matches[1];
  }
  else
  {
    $message .= 'NOTICE: TAM' . $tam . ' status change could have failed, should be ' . $mode . ' now, but I don\'t now if it actually is. Check your check section in the script.';
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
