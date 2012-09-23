<?php
// load the config
require_once('fritzbox.conf.php');
 
// load the fritzbox_api class
require_once('../lib/fritzbox_api.class.php');

if (count($argv) < 2 || $argv[1] != "--overwrite") {
	print "CAUTION: This will delete all current entries in your fritzbox phonebook";
	print $newline."call with ".$argv[0]." --overwrite".$newline;
	die();
}

// do the job
try
{
  $fritz = new fritzbox_api($password, $fritzbox_ip);
  $formfields = array(
			'PhonebookId' => '0',
			'PhonebookImportFile' => '@'.$fonbook_import_path.';type=text/xml'
  );
  
  
  $output = $fritz->doPostFile($formfields);
  
  print "Phonebook imported from " . $fonbook_import_path . $newline;
  
  $fritz = null; // destroy the object to log out
}
catch (Exception $e)
{
  echo $e->getMessage();
}
?>
