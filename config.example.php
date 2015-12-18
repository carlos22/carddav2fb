<?php

// CONFIG

// DNS name of Fritz!Box or IP address
$config['fritzbox_ip'] = 'fritz.box';
$config['fritzbox_ip_ftp'] = 'fritz.box';

// user name/password to access Fritz!Box
$config['fritzbox_user'] = 'fb_username';
$config['fritzbox_pw'] = 'fb_password';
//$config['fritzbox_force_local_config'] = true;

// number of the internal phone book and its name
// 0    - main phone book
// 1..n - additional phone books
$config['phonebook_number'] = '0';
$config['phonebook_name'] = 'Telefonbuch';

// optional: write output to file instead of sending it to the Fritz!Box
//$config['output_file'] = '/media/usbdisk/share/phonebook.xml';

// optional: import only contacts of the given groups
//$config['group_filter'] = array('Arzt','Familie','Freunde','Friseur','Geschäftlich','Hotline','Notruf','Restaurant','Shops');

// group name of 'important' callers
$config['group_vip'] = 'VIP';

// base path of USB storage of Fritz!Box under which the path 'FRITZ\fonpix' could be found
// '' -> use internal fritzbox storage
//$config['usb_disk'] = 'Generic-FlashDisk-01';

// multiple carddav adressbooks could be specified and will be merged together.

// first
$config['carddav'][0] = array(
  // URL of first CardDAV address book on cloud storage
  'url' => 'https://raspserver/owncloud/remote.php/carddav/addressbooks/fritzbox/fb_contacts',
  // user name/password for CardDAV access
  'user' => 'oc_username',
  'pw' => 'oc_password',
  // vcf extension
  'extension' => '.vcf'
);

// second
//$config['carddav'][1] = array(
//  'url' => 'https://raspserver/owncloud/remote.php/carddav/addressbooks/fritzbox/fb_contacts_second',
//  'user' => 'oc_username',
//  'pw' => 'oc_password',
//  'extension' => '.vcf'
//);
