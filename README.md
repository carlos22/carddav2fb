# CardDAV contacts import for AVM FRITZ!Box

Features:

* Allows to import CardDAV-based VCard contacts (e.g. from 'owncloud') to a phonebook in a AVM FRITZ!Box
* CardDAV import includes photo images specified in VCards
* No modification of FRITZ!Box firmware (aka FRITZ!OS) required
* Definition of multiple CardDAV accounts and "folders" possible
* Format of full name in FRITZ!Box phonebook can be designed

**CAUTION: This script will overwrite your current contacts in the FritzBox without any warning!**

## Information

This version of carddav2fb is a forked version from jens-maus (https://github.com/jens-maus/carddav2fb.git) and adding support for convenient image upload, different FRITZ!Box base paths (for example for FRITZ!Box 7490 (UI) OS: 6.50) and full name design support. 

## Requirements

PHP version 5.3.6 or higher is required.

## Installation

 Checkout the carddav2fb sources including its related subprojects using the following command:

		git clone https://github.com/holzhannes/carddav2fb.git

Now you should have everything setup and checked out to a 'carddav2fb' directory.

### Configuration
1. Make sure you have `System -> FRITZ!Box-Users -> Login via Username+Password` in your FRITZ!Box activated.
2. Make sure you have a separate user created under `System -> FRITZ!Box-Users` for which the following access rights have been granted: 
  * `FRITZ!Box settings` (required to upload telephone book data)
  * `Access to NAS content` (required to upload photos via ftp).
3. Make sure the telephone book you are going to update via carddav2fb exists on the FRITZ!Box, otherwise the upload will fail.
4. Copy `config.example.php` to `config.php` and adapt it to your needs including setting the FRITZ!Box user settings.
5. Copy as many `initializing images` from the folder `init-images` to your FRITZ!Box `fonpix` folder.

## Usage

### Ubuntu

1. Install PHP5, PHP-curl and PHP-ftp module:

		sudo apt-get install php5-cli php5-curl php5-ftp

2. Open a Terminal and execute:

		php carddav2fb.php

### Windows

1. Download PHP from [php.net](http://windows.php.net/download/). Extract it to `C:\PHP`.
2. Start -> cmd. Run `C:\PHP\php.exe C:\path\to\carddav2fb\carddav2fb.php`

## config.php Example (owncloud)

	$config['fritzbox_ip'] = 'fritz.box';
	$config['fritzbox_user'] = '<USERNAME>';
	$config['fritzbox_pw'] = '<PASSWORD>';
	$config['phonebook_number'] = '0';
	$config['phonebook_name'] = 'Telefonbuch';
	$config['fritzbox_path'] = 'file:///var/media/ftp/';
	// full name format options default 0
	// parts in '' will only added if existing and switched to true in config
	// 0 =  'Prefix' Lastname, Firstname, 'Additional Names', 'Suffix', 'orgname'
	// 1 =  'Prefix' Firstname Lastname 'AdditionalNames' 'Suffix' '(orgname)'
	// 2 =  'Prefix' Firstname 'AdditionalNames' Lastname 'Suffix' '(orgname)'
	$config['fullname_format'] = 0;

	// fullname parts
	$config['prefix'] = false; // include prefix in fullname if existing
	$config['suffix'] = false; // include suffix in fullname if existing
	$config['addnames'] = false; // include additionalnames in fullname if existing
	$config['orgname'] = false; // include organisation (company) in fullname if existing
	
	// first
	$config['carddav'][0] = array(
	  'url' => 'https://<HOSTNAME>/remote.php/carddav/addressbooks/<USERNAME>/contacts',
	  'user' => '<USERNAME>',
	  'pw' => '<PASSWORD>'
	);

## Note
This script is using third-party libraries for downloading VCards from CardDAV servers based on the following packages
* CardDAV-PHP (https://github.com/jens-maus/CardDAV-PHP.git)
* FRITZ!Box-API-PHP (https://github.com/jens-maus/fritzbox_api_php.git)
* VCard-Parser (https://github.com/jens-maus/vCard-parser.git)

## License
This script is released under Public Domain.

## Authors
Copyright (c) 2012-2015 Karl Glatz, Martin Rost, Jens Maus
