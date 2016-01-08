<?php
/**
 * CardDAV to FritzBox! XML (automatic upload)
 * inspired by http://www.wehavemorefun.de/fritzbox/Hochladen_eines_MySQL-Telefonbuchs
 * 
 * Requirements: 
 *   php5, php5-curl, php5-ftp
 * 
 * used libraries: 
 *  *  vCard-parser <https://github.com/nuovo/vCard-parser> (LICNECE: unknown)
 *  *  CardDAV-PHP <https://github.com/graviox/CardDAV-PHP>(LICENCE: AGPLv3)
 *  *  fritzbox_api_php <https://github.com/carlos22/fritzbox_api_php> (LICENCE: CC-by-SA 3.0)
 * 
 * LICENCE (of this file): MIT
 * 
 * Autors: Karl Glatz (original author)
 *         Martin Rost
 *         Jens Maus <mail@jens-maus.de>
 *
 * version 1.10 2015-06-11
 *
 */
error_reporting(E_ALL);
setlocale(LC_ALL, 'de_DE.UTF8');

$php_min_version = '5.3.6';

if (version_compare(PHP_VERSION, $php_min_version) < 0) {
  print 'ERROR: PHP version '.$php_min_version.' is required. Found version: ' . PHP_VERSION . PHP_EOL;
  exit(1);
}

require_once('lib/CardDAV-PHP/carddav.php');
require_once('lib/vCard-parser/vCard.php');
require_once('lib/fritzbox_api_php/fritzbox_api.class.php');

if ($argc == 2) {
  $config_file_name = $argv[1];
} else {
  $config_file_name = __DIR__ . '/config.php';
}

// default/fallback config options
$config['tmp_dir'] = sys_get_temp_dir();
$config['fritzbox_ip'] = 'fritz.box';
$config['fritzbox_ip_ftp'] = 'fritz.box';
$config['fritzbox_force_local_login'] = false;
$config['phonebook_number'] = '0';
$config['phonebook_name'] = 'Telefonbuch';
$config['usb_disk'] = '';
$config['fritzbox_path'] = 'file:///var/media/ftp/';
$config['fullname_format'] = 0; // see config.example.php for options
$config['prefix'] = false;
$config['suffix'] = false;
$config['addnames'] = false;
$config['orgname'] = false;

if(is_file($config_file_name)) {
  require($config_file_name);
} else {
  print 'ERROR: No '.$config_file_name.' found, please take a look at config.example.php and create a '.$config_file_name.' file!'.PHP_EOL;
  exit(1);
}

// ---------------------------------------------
// MAIN
print "carddav2fb.php - CardDAV to FRITZ!Box conversion tool" . PHP_EOL;
print "Copyright (c) 2012-2015 Karl Glatz, Martin Rost, Jens Maus" . PHP_EOL . PHP_EOL;

$client = new CardDAV2FB($config);

// read vcards from webdav
print 'Retrieving VCards from all CardDAV server(s):' . PHP_EOL;
$client->get_carddav_entries();
print 'Done.' . PHP_EOL;

flush(); // in case this script runs by php-cgi

// transform them to a fritzbox compatible xml file
print 'Converting VCards to FritzBox XML format:' . PHP_EOL;
$client->build_fb_xml();
print 'Done.' . PHP_EOL;

flush(); // in case this script runs by php-cgi

// upload the XML-file to the FRITZ!Box (CAUTION: this will overwrite all current entries in the phone book!!)
print 'Upload data to FRITZ!Box @ ' . $config['fritzbox_ip'] . PHP_EOL;
$client->upload_to_fb();
print 'Done.' . PHP_EOL;

flush(); // in case this script runs by php-cgi

// ---------------------------------------------
// Class definition
class CardDAV2FB {

  protected $entries = array();
  protected $fbxml = "";
  protected $config = null;
  protected $tmpdir = null;

  public function __construct($config) {
    $this->config = $config;

    // create a temp directory where we store photos
    $this->tmpdir = $this->mktemp($this->config['tmp_dir']);
  }

  public function __destruct() {
    // remote temp directory
    $this->rmtemp($this->tmpdir);
  }

  // Source: https://php.net/manual/de/function.tempnam.php#61436
  public function mktemp($dir, $prefix='', $mode=0700) {
    if (substr($dir, -1) != '/') {
      $dir .= '/';
    }

    do {
      $path = $dir.$prefix.mt_rand(0, 9999999);
    } while (!mkdir($path, $mode));

    return $path;
  }

  public function rmtemp($dir) {
    if (is_dir($dir)) {
      $objects = scandir($dir);
      foreach ($objects as $object) {
        if ($object != "." && $object != "..") {
          if (filetype($dir."/".$object) == "dir") rrmdir($dir."/".$object); else unlink($dir."/".$object);
        }
      }
      reset($objects);
      rmdir($dir);
    }
  }

  public function base64_to_jpeg( $inputfile, $outputfile ) {
    /* read data (binary) */
    $ifp = fopen( $inputfile, "rb" );
    $imageData = fread( $ifp, filesize( $inputfile ) );
    fclose( $ifp );
    /* encode & write data (binary) */
    $ifp = fopen( $outputfile, "wb" );
    fwrite( $ifp, base64_decode( $imageData ) );
    fclose( $ifp );
    /* return output filename */
    return( $outputfile );
  }

  public function get_carddav_entries() {
    $entries = array();
    $imgseqfname = 1;

    foreach($this->config['carddav'] as $conf) {
      print " " . $conf['url'] . PHP_EOL;
      $carddav = new CardDavPHP\CardDavBackend($conf['url']);
      $carddav->setAuth($conf['user'], $conf['pw']);

      // set the vcard extension in case the user
      // defined it in the config
      if(isset($conf['extension'])) {
        $carddav->setVcardExtension($conf['extension']);
      }

      // retrieve data from the CardDAV server now
      $xmldata =  $carddav->get();

      // identify if we received UTF-8 encoded data from the
      // CardDAV server and if not reencode it since the FRITZ!Box
      // requires UTF-8 encoded data
      if(iconv('utf-8', 'utf-8//IGNORE', $xmldata) != $xmldata)
        $xmldata = utf8_encode($xmldata);

      // read raw_vcard data from xml response
      $raw_vcards = array();
      $xmlvcard = new SimpleXMLElement($xmldata);

      foreach($xmlvcard->element as $vcard_element)
      {
        $id = $vcard_element->id->__toString();
        $value = (string)$vcard_element->vcard->__toString();
        $raw_vcards[$id] = $value;
      }

      print "  " . count($raw_vcards) . " VCards retrieved" . PHP_EOL;

      // parse raw_vcards
      $result = array();
      foreach($raw_vcards as $v) {
        $vcard_obj = new vCard(false, $v);
        $addnames = '';
        $prefix = '';
        $suffix = '';
        $orgname = '';
        // Build name Parts if existing ans switch to true in config
        if ($name_arr['prefix'] > '' AND $this->config['prefix']) { $prefix = $name_arr['prefix'];} //prefix
		if ($name_arr['suffix'] > '' AND $this->config['suffix']) { $suffix = ' '.$name_arr['suffix'];} //suffix
		if ($name_arr['additionalnames'] > '' AND $this->config['addnames']) { $addnames = ' '.$name_arr['additionalnames'];}//additionalnames
		if ($name_arr['name'] > '' AND $this->config['orgname']) { $orgname = ' ('.$name_arr['name'].')';} //orgname
        
        switch ($this->config['fullname_format']) {
    	case 0:
			// Format 'only if exist and switched on': 'Prefix' Lastname, Firstname, 'Additional Names', 'Suffix', '(orgname)'
			$name_arr = $vcard_obj->n[0];
			$name = trim($prefix.' '.$name_arr['lastname'].', '.$name_arr['firstname'].$addnames.$suffix.$orgname);
			break;
		case 1:
			// Format 'only if exist and switched on': 'Prefix' Firstname Lastname 'AdditionalNames' 'Suffix' '(orgname)'
			$name_arr = $vcard_obj->n[0];
			$name = trim($prefix.' '.$name_arr['firstname'].' '.$name_arr['lastname'].$addnames.$suffix.$orgname);
			break;
		case 3:
			// Format 'only if exist and switched on': 'Prefix' Firstname 'AdditionalNames' Lastname 'Suffix' '(orgname)'
			$name_arr = $vcard_obj->n[0];
			$name = trim($prefix.' '.$name_arr['firstname'].' '.$addnames.$name_arr['lastname'].$suffix.$orgname);
			break;
        }

        // if name is empty we take organization instead
        if(empty($name)) {
          $name_arr = $vcard_obj->org[0];
          $name = $name_arr['name'];
        }

        // format filename of contact photo; remove special letters
        if ($vcard_obj->photo) {
        	$photo = $imgseqfname;
        	$imgseqfname++;
        } else {
          $photo = '';
        }

        // phone
        $phone_no = array();
        if ($vcard_obj->categories) {
          $categories = $vcard_obj->categories[0];
        } else {
          $categories = array('');
        }

        // e-mail addresses
        $email_add = array();

        if (in_array($this->config['group_vip'],$categories)) {
          $vip = 1;
        } else {
          $vip = 0;
        }

        if (array_key_exists('group_filter',$this->config)) {
          $add_entry = 0;
          foreach($this->config['group_filter'] as $group_filter) {
            if (in_array($group_filter,$categories)) {
              $add_entry = 1;
              break;
            }
          }
        } else {
          $add_entry = 1;
        }

        if ($add_entry == 1) {
          foreach($vcard_obj->tel as $t) {

            $prio = 0;
            if (!is_array($t) || empty($t['type'])) {
              $type = "mobile";
              $phone_number = $t;
            } else {
              $phone_number = $t['value'];

              $typearr_lower = unserialize(strtolower(serialize($t['type'])));

              // find out priority
              if (in_array("pref", $typearr_lower)) {
                $prio = 1;
              }

              // set the proper type
              if (in_array("work", $typearr_lower)) {
                $type = "work";
              }
              elseif (in_array("cell", $typearr_lower)) {
                $type = "mobile";
              }
              elseif (in_array("home", $typearr_lower)) {
                $type = "home";
              }
              elseif (in_array("fax", $typearr_lower)) {
                $type = "fax_work";
              }
              elseif (in_array("other", $typearr_lower)) {
                $type = "other";
              }
              elseif (in_array("dom", $typearr_lower)) {
                $type = "other";
              }
              else {
                continue;
              }
            }
            $phone_no[] =  array("type"=>$type, "prio"=>$prio, "value" => $this->_clear_phone_number($phone_number));
          }

          // request email address and type
          if ($vcard_obj->email){
            foreach($vcard_obj->email as $e) {
              if (empty($e['type'])) {
                $type_email = "work";
                $email = $e;
              } else {
                $email = $e['value'];
                $typearr_lower = unserialize(strtolower(serialize($e['type'])));
                if (in_array("work", $typearr_lower)) {
                  $type_email = "work";
                }
                elseif (in_array("home", $typearr_lower)) {
                  $type_email = "home";
                }
                elseif (in_array("other", $typearr_lower)) {
                  $type_email = "other";
                }
                else {
                  continue;
                }
              }

              // DEBUG: print out the email address on the console
              //print $type_email.": ".$email."\n";

              $email_add[] = array("type"=>$type_email, "value" => $email);
            }
          }
          $entries[] = array("realName" => $name, "telephony" => $phone_no, "email" => $email_add, "vip" => $vip, "photo" => $photo, "photo_data" => $vcard_obj->photo);
        }
      }
    }

    $this->entries = $entries;
  }

  private function _clear_phone_number($number) {
    return preg_replace("/[^0-9+]/", "", $number);
    // return $number;
  }

  public function build_fb_xml() {

    if(empty($this->entries)) {
      throw new Exception('No entries available! Call get_carddav_entries or set $this->entries manually!');
    }

    // create FB XML in utf-8 format
    $root = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><phonebooks><phonebook></phonebook></phonebooks>');
    $pb = $root->phonebook;
    $pb->addAttribute("name",$this->config['phonebook_name']);

    foreach($this->entries as $entry) {

        $contact = $pb->addChild("contact");
        $contact->addChild("category", $entry['vip']);
        $person = $contact->addChild("person");
        $person->addChild("realName", $this->_convert_text($entry['realName']));

        echo " VCard: " . utf8_decode($entry['realName']) . PHP_EOL;

        // telephone: put the phonenumbers into the fritzbox xml file
        $telephony = $contact->addChild("telephony");
        $id = 0;
        foreach($entry['telephony'] as $tel) {
          $num = $telephony->addChild("number", $tel['value']);
          $num->addAttribute("type", $tel['type']);
          $num->addAttribute("vanity","");
          $num->addAttribute("prio", $tel['prio']);
          $num->addAttribute("id", $id);
          $id++;

          print "  Added phone: " . $tel['value'] . " (" . $tel['type'] . ")" . PHP_EOL;
        }

        // output a warning if no telephone number was found
        if($id == 0)
        {
          print "  WARNING: no phone entry found. VCard will be ignored." . PHP_EOL;
        }

        // email: put the email addresses into the fritzbox xml file
        $email = $contact->addChild("services");
        $id = 0;
        foreach($entry['email'] as $mail) {
          $mail_adr = $email->addChild("email", $mail['value']);
          $mail_adr->addAttribute("classifier", $mail['type']);
          $mail_adr->addAttribute("id", $id);
          $id++;

          print "  Added email: " . $mail['value'] . " (" . $mail['type'] . ")" . PHP_EOL;
        }

        // check for a photo being part of the VCard
        if(($entry['photo']) and ($entry['photo_data'])) {
          // get photo, rename, base64 convert and save as jpg
          $photo = $entry['photo'];
          $photo_data = $entry['photo_data'][0]['value'];
          $photo_file = $this->tmpdir . '/' . basename($photo) . ".jpg";
          file_put_contents($photo_file . ".b64", $photo_data);

          // convert base64 representation to jpg and delete tempfile afterwards
          $this->base64_to_jpeg($photo_file . ".b64", $photo_file);
          unlink($photo_file . ".b64");

          // add contact photo to xml
          $person->addChild("imageURL",$this->config['fritzbox_path'].$this->config['usb_disk']."/FRITZ/fonpix/".$entry['photo'].".jpg");

          print "  Added photo: " . basename($photo_file) . PHP_EOL;
        }


        $contact->addChild("services");
        $contact->addChild("setup");
        $contact->addChild("mod_time", (string)time());
    }

    $this->fbxml = $root->asXML();
  }

  public function _convert_text($text) {
    $text = htmlspecialchars($text);
    //$text = iconv("UTF-8", "ISO-8859-1//IGNORE", $text);
    return $text;
  }

  public function _concat ($text1,$text2) {
    if ($text1 == '') {
      return $text2;
    }
    elseif ($text2 == '') {
      return $text1;
    }
    else
    {
      return $text1.", ".$text2;
    }
  }

  public function _parse_fb_result($text) {
    preg_match("/\<h2\>([^\<]+)\<\/h2\>/", $text, $matches);
    if($matches)
      return $matches[1];
    else
      return "Error while uploading xml to fritzbox";
  }

  public function upload_to_fb() {

    // if the user wants to save the xml to a separate file, we do so now
    if (array_key_exists('output_file',$this->config)) {
      $output = fopen($this->config['output_file'], 'w');
      if ($output) {
        fwrite($output, $this->fbxml);
        fclose($output);
      }
      return 0;
    };

    // now we upload the photo jpgs first being stored in the
    // temp directory.

    // perform an ftps-connection to copy over the photos to a specified directory
    $ftp_server = $this->config['fritzbox_ip_ftp'];
    $conn_id = ftp_ssl_connect($ftp_server);
    ftp_set_option($conn_id, FTP_TIMEOUT_SEC, 60);
    $login_result = ftp_login($conn_id, $this->config['fritzbox_user'], $this->config['fritzbox_pw']);
    ftp_pasv($conn_id, true);

    // create remote photo path on FRITZ!Box if it doesn't exist
    $remote_path = $this->config['usb_disk']."/FRITZ/fonpix";
    if(ftp_nlist($conn_id, $remote_path) == false)
    {
      ftp_mkdir($conn_id, $remote_path);
    }

    // now iterate through all jpg files in tempdir and upload them
    $dir = new DirectoryIterator($this->tmpdir);
    foreach($dir as $fileinfo)
    {
      if(!$fileinfo->isDot())
      {
        if($fileinfo->getExtension() == "jpg")
        {
          $file = $fileinfo->getFilename();
          print " FTP-Upload: " . $file . PHP_EOL;
          if(!ftp_put($conn_id, $remote_path."/".$file, $fileinfo->getPathname(), FTP_BINARY))
          {
            // retry when a fault occurs.
            print "  WARNING: an error occurred while uploading file ".$fileinfo->getFilename()." - retrying" . PHP_EOL;
            $conn_id = ftp_ssl_connect($ftp_server);
            $login_result = ftp_login($conn_id, $this->config['fritzbox_user'], $this->config['fritzbox_pw']);
            ftp_pasv($conn_id, true);
            if(!ftp_put($conn_id, $remote_path."/".$file, $fileinfo->getPathname(), FTP_BINARY))
            {
              print "  ERROR: an error occurred while uploading file ".$fileinfo->getFilename()." - giving up" . PHP_EOL;
            }
          }
        }
      }
    }

    // close ftp connection
    ftp_close($conn_id);

    // lets post the phonebook xml to the FRITZ!Box
    print " Uploading Phonebook XML" . PHP_EOL;
    try
    {
      $fritz = new fritzbox_api($this->config['fritzbox_pw'],
        $this->config['fritzbox_user'],
        $this->config['fritzbox_ip'],
        $this->config['fritzbox_force_local_login']);
      $formfields = array(
      'PhonebookId' => $this->config['phonebook_number']
      );

      $filefileds = array('PhonebookImportFile' => array(
       'type' => 'text/xml',
       'filename' => 'updatepb.xml',
       'content' => $this->fbxml,
       )
      );

      $raw_result =  $fritz->doPostFile($formfields, $filefileds);   // send the command
      $msg = $this->_parse_fb_result($raw_result);
      $fritz = null;  // destroy the object to log out

      print "  FRITZ!Box returned message: '" . $msg . "'" . PHP_EOL;
    }
    catch (Exception $e)
    {
      print "  ERROR: " . $e->getMessage() . PHP_EOL;     // show the error message in anything failed
    }
  }
}
?>
