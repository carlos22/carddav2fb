<?php

// CONFIG

// hostname or IP address of Fritz!Box
// Note: Some Boxes are configured to reject login via IP.
//       If that's the case with your box, then you have to specify the hostname.
$config['fritzbox_ip'] = 'fritz.box';
$config['fritzbox_ip_ftp'] = 'fritz.box';

// user name/password to access Fritz!Box
$config['fritzbox_user'] = 'fb_username';
$config['fritzbox_pw'] = 'fb_password';
//$config['fritzbox_force_local_login'] = true;

// number of the internal phone book and its name
// 0    - main phone book
// 1..n - additional phone books
$config['phonebook_number'] = '0';
$config['phonebook_name'] = 'Telefonbuch';

// Fullname format options
// 'only if exist and switched to true here in config'
// 0: "Prefix Lastname, Firstname AdditionalNames Suffix (orgname)"
// 1: "Prefix Firstname Lastname AdditionalNames Suffix (orgname)"
// 2: "Prefix Firstname AdditionalNames Lastname Suffix (orgname)"
$config['fullname_format'] = 0;

// Fullname parts
$config['prefix'] = false; // include prefix in fullname if existing
$config['suffix'] = false; // include suffix in fullname if existing
$config['addnames'] = false; // include additionalnames in fullname if existing
$config['orgname'] = false; // include organisation (company) in fullname if existing

// Quickdial starting keyword in notes
//$config['quickdial_keyword'] = 'Quickdial:'; // once activated you may add 'Quickdial:+49030123456:**709' to the contact note field and the number will set as quickdialnumber. You may add more quickdials for a single contact each in a new line

// optional: write output to file of sending it to the Fritz!Box
//$config['output_file'] = '/media/usbdisk/share/phonebook.xml';
// optional: once activated phonebook will also be upload to FRITZ!Box if changes are detectet (output_file must be set)
//$config['output_and_upload'] = false;

// optional: import only contacts of the given groups
//$config['group_filter'] = array('Arzt','Familie','Freunde','Friseur','Geschäftlich','Hotline','Notruf','Restaurant','Shops');

// group name of 'important' callers
$config['group_vip'] = 'VIP';

// base path of USB storage of Fritz!Box under which the path 'FRITZ\fonpix' could be found
// '' -> use internal fritzbox storage
//$config['usb_disk'] = 'Generic-FlashDisk-01';

// many version Fritz!Box use 'file:///var/media/ftp/' others 'file:///var/InternerSpeicher/' to check just export an your current phonebook and have a look at any imageURL tag `<imageURL>file:///var/media/ftp/(HERE_config_from:usb_disk)/FRITZ/fonpix/9.jpg</imageURL>`. 
//$config['fritzbox_path'] = 'file:///var/media/ftp/';

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
