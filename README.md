# CardDAV contacts import for AVM FRITZ!Box

This is an entirely simplified version of https://github.com/jens-maus/carddav2fb. The Vcard parser has been replaced by an extended version of https://github.com/jeroendesloovere/vcard.

## Requirements

  * PHP 7.0 (`apt-get install php7.0 php7.0-curl php7.0-mbstring php7.0-xml`)
  * Composer (follow the installation guide at https://getcomposer.org/download/)

## Installation

Install requirements

    git clone https://github.com/andig/carddav2fb.git
    cd carddav2fb
    composer install

edit `config.example.php` and save as `config.php`

## Usage

List all commands:

    php carddav2fb.php list

Complete processing:

    php carddav2fb.php run

Get help for a command:

    php carddav2fb.php run -h

## Precondition for using image upload (command -i)

  * your memory (USB stick) is indexed [Heimnetz -> Speicher (NAS) -> Speicher an der FRITZ!Box]
  * ftp access is activ [Heimnetz -> Speicher (NAS) -> Heimnetzfreigabe]
  * you use an standalone user (NOT! dslf-config) which has explicit permissions for FRITZ!Box settings, access to NAS content and read/write permission to all available memory [System -> FRITZ!Box-Benutzer -> [user] -> Berechtigungen]

## License
This script is released under Public Domain, some parts under MIT license. Make sure you understand which parts are which.

## Authors
Copyright (c) 2012-2017 Karl Glatz, Martin Rost, Jens Maus, Johannes Freiburger, Andreas Götz, Volker Püschel
