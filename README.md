# CardDAV contacts import for AVM FRITZ!Box

This is an entirely simplified version of https://github.com/jens-maus/carddav2fb. The Vcard parser has been replaced by an extended version of https://github.com/jeroendesloovere/vcard.

## Features

  * download from any number of CardDAV servers
  * selection (include/exclude) by categories or groups (e.g. iCloud)
  * upload of contact pictures to display them on the FRITZ!Fon (handling see below)
  * transfer of quick dial and vanity numbers (see wiki for handling details)
  * if more than nine phone numbers are included, the contact will be divided into a corresponding number of phonebook entries (any existing email addresses are assigned to the first set [there is no quantity limit!])
  * phone numbers are sorted by type. The order of the conversion values ('phoneTypes') determines the order in the phone book entry
  * the contact's UID of the CardDAV server is added to the phonebook entry (not visible in the FRITZ! Box GUI)

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
This script is released under Public Domain, some parts under GNU AGPL or MIT license. Make sure you understand which parts are which.

## Authors
Copyright (c) 2012-2017 Karl Glatz, Christian Putzke, Martin Rost, Jens Maus, Johannes Freiburger, Andreas Götz, Volker Püschel
