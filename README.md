# CardDAV contacts import for AVM FRITZ!Box

This is an entirely simplified version of https://github.com/jens-maus/carddav2fb. The Vcard parser has been replaced by an extended version of https://github.com/jeroendesloovere/vcard.

## Requirements
PHP 7.0 (apt-get install php7.0 php7.0-curl php7.0-mbstring php7.0-xml)
Composer (follow the installation guide at https://getcomposer.org/download/)

## Installation
Install requirements
cammand: git clone https://github.com/andig/carddav2fb.git
change into the new carddav2fb directory
command: composer install
edit config.example.php and save as config.php

## Usage
command: php carddav2fb.php run

## License
This script is released under Public Domain, some parts under MIT license. Make sure you understand which parts are which.

## Authors
Copyright (c) 2012-2017 Karl Glatz, Martin Rost, Jens Maus, Johannes Freiburger, Andreas Götz
