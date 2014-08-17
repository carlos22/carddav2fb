## CardDAV contacts import for AVM FritzBox

Features:

* Allows to import CardDAV contacts (e.g. from 'owncloud') to an AVM FritzBox
* No modification of the FirtzBox firmware (aka FritzOS) required
* Multiple CardDAV accounts and "folders" can be specified 

**CAUTION: This script will overwrite your current contacts in the FritzBox without any warning!**

### Information

This version of carddav2fb is a forked version from carlos22 with updates applied being published at http://www.ip-phone-forum.de/showthread.php?t=267477. In addition to being compatible to newer Fritz!OS versions it also features two fixes regarding OSX generated vCards.

### Installation

 1. Use git to checkout carddav2fb from github

		git clone https://github.com/jens-maus/carddav2fb.git

 2. Initialize the git submodules

		cd carddav2fb
		git submodule init
		git submodule update
Now you should have everything setup and checked out to a 'carddav2fb' directory.

### Configuration
Open `config.example.php`, adapt it to your needs and save it as `config.php`

### Usage

#### Ubuntu

1. Install PHP and PHP-Curl

		sudo apt-get install php5-cli php5-curl

2. Open a Terminal and execute

		php carddav2fb.php

#### Windows

1. Download PHP from [php.net](http://windows.php.net/download/). Extract it to `C:\PHP`.
2. Start -> cmd. Run `C:\PHP\php.exe C:\path\to\carddav2fb\carddav2fb.php`

### config.php Example (owncloud)

	$config['fritzbox_ip'] = 'fritz.box';
	$config['fritzbox_user'] = '<USERNAME>';
	$config['fritzbox_pw'] = '<PASSWORD>';
	$config['phonebook_number'] = '0';
	$config['phonebook_name'] = 'Telefonbuch';
	
	// first
	$config['carddav'][0] = array(
	  'url' => 'https://<HOSTNAME>/remote.php/carddav/addressbooks/<USERNAME>/contacts',
	  'user' => '<USERNAME>',
	  'pw' => '<PASSWORD>'
	);
