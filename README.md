# Import CardDAV contacts into a AVM FritzBox

Features:

* No modification of the FirtzBox firmware (aka FritzOS) is required
* Multiple CardDAV accounts and "folders" can specified 

**CAUTION: This script will overwrite your current contacts in the FritzBox without any warning!**

## Installation

Download the [current version](https://github.com/carlos22/carddav2fb/downloads). Extract it.

### Configuration
Open `config.example.php`, adapt it to your needs and save it as `config.php`

### Ubuntu

Install PHP and PHP-Curl

	sudo apt-get install php5-cli php5-curl

Open a Terminal and just run `php /path/to/carddav2fb/carddav2fb.php`

### Windows
Download PHP from [php.net](http://windows.php.net/download/). Extract it to `C:\PHP`.
Start -> cmd. Run `C:\PHP\php.exe C:\path\to\carddav2fb\carddav2fb.php`


## Releated
See also: [fritzbox_api_php](https://github.com/carlos22/fritzbox_api_php)

Inspired by [Hochladen eines MySQL-Telefonbuchs](http://www.wehavemorefun.de/fritzbox/Hochladen_eines_MySQL-Telefonbuchs) (ruby)
