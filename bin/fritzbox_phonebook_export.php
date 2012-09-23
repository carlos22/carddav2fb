<?php
// load the config
require_once('fritzbox.conf.php');
 
// load the fritzbox_api class
require_once('../lib/fritzbox_api.class.php');

// do the job
try
{
  $fritz = new fritzbox_api($password, $fritzbox_ip);
  $formfields = array(
			'PhonebookId' => '0',
			'PhonebookExportName' => 'Telefonbuch',
			'PhonebookExport' => ''
  );
  
  $output = $fritz->doPostFile($formfields);
  file_put_contents($fonbook_export_path, $output);
  print "Phonebook exported to " . $fonbook_export_path . $newline;
  
  $fritz = null; // destroy the object to log out
}
catch (Exception $e)
{
  echo $e->getMessage();
}
?>
