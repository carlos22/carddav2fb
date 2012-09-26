<?php

// CONFIG
$config['fritzbox_url'] = 'fritz.box';
$config['fritzbox_port'] = 80;
$config['fritzbox_pw'] = false;

// multiple carddav adressbooks could be specified

// first
$config['carddav'][0] = array(
	'url' => 'https://example.com/carddav/user@domian.de/MyContacts', 
	'user' => 'user@domain.de', 
	'pw' => 'supersecret'
);

// second
//$config['carddav'][1] = array(
//	'url' => 'https://carddav.example.com/contacts', 
//	'user' => 'user', 
//	'pw' => 'password'
//);

