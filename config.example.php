<?php

// CONFIG
$config['fritzbox_ip'] = 'fritz.box';
$config['fritzbox_user'] = 'fb_username';
$config['fritzbox_pw'] = 'fb_password';
$config['phonebook_number'] = '0';
$config['phonebook_name'] = 'FBF_Name';
//$config['output_file'] = '/media/usbdisk/share/phonebook.xml';
//$config['group_filter'] = array('Arzt','Familie','Freunde','Friseur','Geschäftlich','Hotline','Notruf','Restaurant','Shops');
$config['group_vip'] = 'VIP';
//$config['usb_disk'] = 'USBDISK2-0-01';

// multiple carddav adressbooks could be specified and will be merged together.

// first
$config['carddav'][0] = array(
	'url' => 'https://raspserver/owncloud/remote.php/carddav/addressbooks/fritzbox/fb_contacts', 
	'user' => 'oc_username', 
	'pw' => 'oc_password'
);

// second
//$config['carddav'][1] = array(
//	'url' => 'https://raspserver/owncloud/remote.php/carddav/addressbooks/fritzbox/fb_contacts_second', 
//	'user' => 'oc_username', 
//	'pw' => 'oc_password'
//);
