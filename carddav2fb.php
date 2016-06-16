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
 *         Johannes Freiburger
 *
 */
error_reporting(E_ALL);
setlocale(LC_ALL, 'de_DE.UTF8');

// Version identifier for CardDAV2FB
$carddav2fb_version = '1.11 (2016-05-12)';

// check for the minimum php version
$php_min_version = '5.3.6';
if(version_compare(PHP_VERSION, $php_min_version) < 0)
{
  print 'ERROR: PHP version ' . $php_min_version . ' is required. Found version: ' . PHP_VERSION . PHP_EOL;
  exit(1);
}

require_once('lib/CardDAV-PHP/carddav.php');
require_once('lib/vCard-parser/vCard.php');
require_once('lib/fritzbox_api_php/fritzbox_api.class.php');

if($argc == 2)
  $config_file_name = $argv[1];
else
  $config_file_name = __DIR__ . '/config.php';

// default/fallback config options
$config['tmp_dir'] = sys_get_temp_dir();
$config['fritzbox_ip'] = 'fritz.box';
$config['fritzbox_ip_ftp'] = $config['fritzbox_ip'];
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
$config['build_photos'] = true;
$config['quickdial_keyword'] = 'Quickdial:';

if(is_file($config_file_name))
  require($config_file_name);
else
{
  print 'ERROR: No ' . $config_file_name . ' found, please take a look at config.example.php and create a ' . $config_file_name . ' file!' . PHP_EOL;
  exit(1);
}

// ---------------------------------------------
// MAIN
print "carddav2fb.php " . $carddav2fb_version . " - CardDAV to FRITZ!Box phonebook conversion tool" . PHP_EOL;
print "Copyright (c) 2012-2016 Karl Glatz, Martin Rost, Jens Maus, Johannes Freiburger" . PHP_EOL . PHP_EOL;

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
class CardDAV2FB
{
  protected $entries = array();
  protected $fbxml = "";
  protected $config = null;
  protected $tmpdir = null;

  public function __construct($config)
  {
    $this->config = $config;

    // create a temp directory where we store photos
    $this->tmpdir = $this->mktemp($this->config['tmp_dir']);
  }

  public function __destruct()
  {
    // remote temp directory
    $this->rmtemp($this->tmpdir);
  }

  // Source: https://php.net/manual/de/function.tempnam.php#61436
  public function mktemp($dir, $prefix = '', $mode = 0700)
  {
    if(substr($dir, -1) != '/')
      $dir .= '/';

    do
    {
      $path = $dir . $prefix . mt_rand(0, 9999999);
    }
    while(!mkdir($path, $mode));

    return $path;
  }

  public function rmtemp($dir)
  {
    if(is_dir($dir))
    {
      $objects = scandir($dir);
      foreach($objects as $object)
      {
        if($object != "." && $object != "..")
        {
          if(filetype($dir . "/" . $object) == "dir")
            rrmdir($dir . "/" . $object); else unlink($dir . "/" . $object);
        }
      }
      reset($objects);
      rmdir($dir);
    }
  }

  public function is_base64($str)
  {
    try
    {
      // Check if there are valid base64 characters
      if(!preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $str))
        return false;

      // Decode the string in strict mode and check the results
      $decoded = base64_decode($str, true);
      if($decoded === false)
        return false;

      // Encode the string again
      if(base64_encode($decoded) === $str)
        return true;
      else
        return false;
    }
    catch(Exception $e)
    {
      // If exception is caught, then it is not a base64 encoded string
      return false;
    }
  }

  public function base64_to_jpeg($inputfile, $outputfile)
  {
    // read data (binary)
    $ifp = fopen($inputfile, "rb");
    $imageData = fread($ifp, filesize($inputfile));
    fclose($ifp);

    // encode & write data (binary)
    $ifp = fopen($outputfile, "wb");
    fwrite($ifp, base64_decode($imageData));
    fclose($ifp);

    // return output filename
    return($outputfile);
  }

  public function get_carddav_entries()
  {
    $entries = array();
    $snum = 0;

    if(is_array($this->config['carddav']))
    {
      foreach($this->config['carddav'] as $conf)
      {
        print " [" . $snum . "]: " . $conf['url'] . " ";
        $carddav = new CardDavPHP\CardDavBackend($conf['url']);
        $carddav->setAuth($conf['user'], $conf['pw']);

        // set the vcard extension in case the user
        // defined it in the config
        if(isset($conf['extension']))
          $carddav->setVcardExtension($conf['extension']);

        // retrieve data from the CardDAV server now
        $xmldata = $carddav->get();

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

        print " " . count($raw_vcards) . " VCards retrieved." . PHP_EOL;

        // parse raw_vcards
        $quick_dial_arr = array();
        foreach($raw_vcards as $v)
        {
          $vcard_obj = new vCard(false, $v);
          $name_arr = null;
          if(isset($vcard_obj->n[0]))
            $name_arr = $vcard_obj->n[0];
          $org_arr = null;
          if(isset($vcard_obj->org[0]))
            $org_arr = $vcard_obj->org[0];
          $addnames = '';
          $prefix = '';
          $suffix = '';
          $orgname = '';
          $formattedname = '';

          // Build name Parts if existing ans switch to true in config
          if(isset($name_arr['prefixes']) and $this->config['prefix'])
            $prefix = trim($name_arr['prefixes']);

          if(isset($name_arr['suffixes']) and $this->config['suffix'])
            $suffix = trim($name_arr['suffixes']);

          if(isset($name_arr['additionalnames']) and $this->config['addnames'])
            $addnames = trim($name_arr['additionalnames']);

          if(isset($org_arr['name']) and $this->config['orgname'])
            $orgname = trim($org_arr['name']);

          if (isset($vcard_obj->fn[0]))
            $formattedname = $vcard_obj->fn[0];

          $firstname = trim($name_arr['firstname']);
          $lastname = trim($name_arr['lastname']);

          // the following section implemented different ways of constructing the
          // final phonebook name entry depending on user preferred settings
          // selectable in the config file. Possible options are:
          //
          // $this->config['fullname_format']:
          //
          // 0: "Prefix Lastname, Firstname AdditionalNames Suffix (orgname)"
          // 1: "Prefix Firstname Lastname AdditionalNames Suffix (orgname)"
          // 2: "Prefix Firstname AdditionalNames Lastname Suffix (orgname)"
          //
          $name = '';
          $format = $this->config['fullname_format'];

          // Prefix
          if(!empty($prefix))
            $name .= $prefix;

          if($format == 0)
          {
            // Lastname
            if(!empty($name) and !empty($lastname))
              $name .= ' ' . $lastname;
            else
              $name .= $lastname;
          }
          else
          {
            // Firstname
            if(!empty($name) and !empty($firstname))
              $name .= ' ' . $firstname;
            else
              $name .= $firstname;
          }

          if($format == 2)
          {
            // AdditionalNames
            if(!empty($name) and !empty($addnames))
              $name .= ' ' . $addnames;
            else
              $name .= $addnames;
          }

          if($format == 0)
          {
            // Firstname
            if(!empty($name) and !empty($firstname))
              $name .= ', ' . $firstname;
            else
              $name .= $firstname;
          }
          else
          {
            // Lastname
            if(!empty($name) and !empty($lastname))
              $name .= ' ' . $lastname;
            else
              $name .= $lastname;
          }

          if($format != 2)
          {
            // AdditionalNames
            if(!empty($name) and !empty($addnames))
              $name .= ' ' . $addnames;
            else
              $name .= $addnames;
          }

          // Suffix
          if(!empty($name) and !empty($suffix))
            $name .= ' ' . $suffix;
          else
            $name .= $suffix;

          // OrgName
          if(!empty($name) and !empty($orgname))
            $name .= ' (' . $orgname . ')';
          else
            $name .= $orgname;

          // make sure to trim whitespaces and double spaces
          $name = trim(str_replace('  ', ' ', $name));

          // perform a fallback to formatted name, if we don't have any name and formatted name is available
          if(empty($name) and !empty($formattedname))
            $name = $formattedname;

          if(empty($name))
          {
            print '  WARNING: No fullname, lastname, orgname or formatted name found!' . PHP_EOL;
            $name = 'UNKNOWN';
          }

          // format filename of contact photo; remove special letters
          if($vcard_obj->photo)
          {
            $photo = str_replace(array(',', '&', ' ', '/', 'ä', 'ö', 'ü', 'Ä', 'Ö', 'Ü', 'ß', 'á', 'à', 'ó', 'ò', 'ú', 'ù', 'í', 'ø'),
            array('', '_', '_', '_', 'ae', 'oe', 'ue', 'Ae', 'Oe', 'Ue', 'ss', 'a', 'a', 'o', 'o', 'u', 'u', 'i', 'oe'), $name);
          }
          else
            $photo = '';

          // phone
          $phone_no = array();
          if($vcard_obj->categories)
            $categories = $vcard_obj->categories[0];
          else
            $categories = array();

          // check for quickdial entry
          if(isset($vcard_obj->note[0]))
          {
            $note = $vcard_obj->note[0];
            $notes = explode($this->config['quickdial_keyword'], $note);
            foreach($notes as $linenr => $linecontent)
            {
              $found = strrpos($linecontent, ":**7");
              if($found > 0)
              {
                $pos_qd_start = strrpos($linecontent, ":**7");
                $quick_dial_for_nr = preg_replace("/[^0-9+]/", "", substr($linecontent, 0, $pos_qd_start));
                $quick_dial_nr = intval(substr($linecontent, $pos_qd_start + 4, 3));
                $quick_dial_arr[$quick_dial_for_nr] = $quick_dial_nr;
              }
            }
          }

          // e-mail addresses
          $email_add = array();
          $vip = isset($this->config['group_vip']) && in_array((string)$this->config['group_vip'], $categories);

          if(array_key_exists('group_filter', $this->config) && is_array($this->config['group_filter']))
          {
            $add_entry = 0;
            foreach($this->config['group_filter'] as $group_filter)
            {
              if(in_array($group_filter, $categories))
              {
                $add_entry = 1;
                break;
              }
            }
          } 
          else
            $add_entry = 1;

          if($add_entry == 1)
          {
            foreach($vcard_obj->tel as $t)
            {
              $prio = 0;
              $quickdial = null;
              
              if(!is_array($t) || empty($t['type']))
              {
                $type = "mobile";
                $phone_number = $t;
              }
              else
              {
                $phone_number = $t['value'];
                
                $phone_number_clean = preg_replace("/[^0-9+]/", "", $phone_number);
                foreach($quick_dial_arr as $qd_phone_nr => $value)
                {
                  if($qd_phone_nr == $phone_number_clean)
                  {
                    //Set quickdial
                    if($value == 1)
                      print "\nWARNING: Quickdial value 1 (**701) is not possible but used! \n";
                    elseif($value >= 100)
                      print "\nWARNING: Quickdial value bigger than 99 (**799) is not possible but used! \n";

                    $quickdial = $value;
                  }
                }

                $typearr_lower = unserialize(strtolower(serialize($t['type'])));

                // find out priority
                if(in_array("pref", $typearr_lower))
                  $prio = 1;

                // set the proper type
                if(in_array("cell", $typearr_lower))
                  $type = "mobile";
                elseif(in_array("home", $typearr_lower))
                  $type = "home";
                elseif(in_array("fax", $typearr_lower))
                  $type = "fax_work";
                elseif(in_array("work", $typearr_lower))
                  $type = "work";
                elseif(in_array("other", $typearr_lower))
                  $type = "other";
                elseif(in_array("dom", $typearr_lower))
                  $type = "other";
                else
                  continue;
              }
              $phone_no[] = array("type"=>$type, "prio"=>$prio, "quickdial"=>$quickdial, "value" => $this->_clear_phone_number($phone_number));
            }

            // request email address and type
            if($vcard_obj->email)
            {
              foreach($vcard_obj->email as $e)
              {
                if(empty($e['type']))
                {
                  $type_email = "work";
                  $email = $e;
                }
                else
                {
                  $email = $e['value'];
                  $typearr_lower = unserialize(strtolower(serialize($e['type'])));
                  if(in_array("work", $typearr_lower))
                    $type_email = "work";
                  elseif(in_array("home", $typearr_lower))
                    $type_email = "home";
                  elseif(in_array("other", $typearr_lower))
                    $type_email = "other";
                  else
                    continue;
                }

                // DEBUG: print out the email address on the console
                //print $type_email.": ".$email."\n";

                $email_add[] = array("type"=>$type_email, "value" => $email);
              }
            }
            $entries[] = array("realName" => $name, "telephony" => $phone_no, "email" => $email_add, "vip" => $vip, "photo" => $photo, "photo_data" => $vcard_obj->photo);
          }
        }

        $snum++;
      }
    }

    $this->entries = $entries;
  }

  private function _clear_phone_number($number)
  {
    return preg_replace("/[^0-9+]/", "", $number);
  }

  public function build_fb_xml()
  {
    if(empty($this->entries))
      throw new Exception('No entries available! Call get_carddav_entries or set $this->entries manually!');

    // create FB XML in utf-8 format
    $root = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><phonebooks><phonebook></phonebook></phonebooks>');
    $pb = $root->phonebook;
    $pb->addAttribute("name", $this->config['phonebook_name']);

    foreach($this->entries as $entry)
    {
      $contact = $pb->addChild("contact");
      $contact->addChild("category", $entry['vip']);
      $person = $contact->addChild("person");
      $person->addChild("realName", $this->_convert_text($entry['realName']));

      echo " VCard: '" . utf8_decode($entry['realName']) . "'" . PHP_EOL;

      // telephone: put the phonenumbers into the fritzbox xml file
      $telephony = $contact->addChild("telephony");
      $id = 0;
      foreach($entry['telephony'] as $tel)
      {
        $num = $telephony->addChild("number", $tel['value']);
        $num->addAttribute("type", $tel['type']);
        $num->addAttribute("vanity", "");
        $num->addAttribute("prio", $tel['prio']);
        $num->addAttribute("id", $id);

        if(isset($tel['quickdial']))
        {
          $num->addAttribute("quickdial", $tel['quickdial']);
          print "  Added quickdial: " . $tel['quickdial'] . " for: " . $tel['value'] . " (" . $tel['type'] . ")" . PHP_EOL;
        }

        $id++;
        print "  Added phone: " . $tel['value'] . " (" . $tel['type'] . ")" . PHP_EOL;
      }

      // output a warning if no telephone number was found
      if($id == 0)
        print "  WARNING: no phone entry found. VCard will be ignored." . PHP_EOL;

      // email: put the email addresses into the fritzbox xml file
      $email = $contact->addChild("services");
      $id = 0;
      foreach($entry['email'] as $mail)
      {
        $mail_adr = $email->addChild("email", $mail['value']);
        $mail_adr->addAttribute("classifier", $mail['type']);
        $mail_adr->addAttribute("id", $id);
        $id++;

        print "  Added email: " . $mail['value'] . " (" . $mail['type'] . ")" . PHP_EOL;
      }

      // check for a photo being part of the VCard
      if(($entry['photo']) and ($entry['photo_data']) and (is_array($entry['photo_data'])) and ($entry['photo_data'][0]))
      {
        // check if 'photo_data'[0] is an array as well because then
        // we have to extract ['value'] and friends.
        if(is_array($entry['photo_data'][0]) and (array_key_exists('value', $entry['photo_data'][0])))
        {
          // check if photo_data really contains JPEG data
          if((array_key_exists('type', $entry['photo_data'][0])) and (is_array($entry['photo_data'][0]['type'])) and
             ($entry['photo_data'][0]['type'][0] == 'jpeg' or $entry['photo_data'][0]['type'][0] == 'jpg' or $entry['photo_data'][0]['type'][0] == 'image/jpeg'))
          {
            // get photo, rename, base64 convert and save as jpg
            $photo_data = $entry['photo_data'][0]['value'];
            $photo_version = substr(sha1($photo_data), 0, 5);
            $photo_file = $this->tmpdir . '/' . "{$entry['photo']}_{$photo_version}.jpg";

            // check for base64 encoding of the photo data and convert it
            // accordingly.
            if(((array_key_exists('encoding', $entry['photo_data'][0])) and ($entry['photo_data'][0]['encoding'] == 'b')) or $this->is_base64($photo_data))
            {
              file_put_contents($photo_file . ".b64", $photo_data);
              $this->base64_to_jpeg($photo_file . ".b64", $photo_file);
              unlink($photo_file . ".b64");
            }
            else
            {
              print "  WARNING: non-base64 encoded photo data found and used." . PHP_EOL;
              file_put_contents($photo_file, $photo_data);
            }

            // add contact photo to xml
            $person->addChild("imageURL", $this->config['fritzbox_path'] . $this->config['usb_disk'] . "FRITZ/fonpix/" . basename($photo_file));

            print "  Added photo: " . basename($photo_file) . PHP_EOL;
          }
          else
           print "  WARNING: Only jpg contact photos are currently supported." . PHP_EOL;
        }
        elseif(substr($entry['photo_data'][0], 0, 4) == 'http')
        {
          // add contact photo to xml
          $person->addChild("imageURL", $entry['photo_data'][0]);

          print "  Added photo: " . $entry['photo_data'][0] . PHP_EOL;
        }
        else
          print "  WARNING: Only VCard embedded photo data or a reference URL is currently supported." . PHP_EOL;
      }

      $contact->addChild("services");
      $contact->addChild("setup");
      $contact->addChild("mod_time", (string)time());
    }

    if($root->asXML() !== false)
      $this->fbxml = $root->asXML();
    else
    {
      print "  ERROR: created XML data isn't well-formed." . PHP_EOL;
      exit(1);
    }
  }

  public function _convert_text($text)
  {
    $text = htmlspecialchars($text);
    return $text;
  }

  public function _concat($text1, $text2)
  {
    if($text1 == '')
      return $text2;
    elseif($text2 == '')
      return $text1;
    else
      return $text1 . ", " . $text2;
  }

  public function _parse_fb_result($text)
  {
    if(preg_match("/\<h2\>([^\<]+)\<\/h2\>/", $text, $matches) == 1 && !empty($matches))
      return $matches[1];
    else
      return "Error while uploading xml to fritzbox";
  }

  public function upload_to_fb()
  {
    // if the user wants to save the xml to a separate file, we do so now
    if(array_key_exists('output_file', $this->config))
    {
      // build md5 hash of previous stored xml without <mod_time> Elements
      $oldphonebhash = md5(preg_replace("/<mod_time>(\\d{10})/","",file_get_contents($this->config['output_file'],'r'),-1,$debugoldtsreplace));
      $output = fopen($this->config['output_file'], 'w');
      if($output)
      {
        fwrite($output, $this->fbxml);
        fclose($output);
        print " Saved to file " . $this->config['output_file'] . PHP_EOL;
      }
	  if (array_key_exists('output_and_upload', $this->config) and $this->config['output_and_upload'])
	  {
	  	$newphonebhash = md5(preg_replace("/<mod_time>(\\d{10})/","",file_get_contents($this->config['output_file'],'r'),-1,$debugnewtsreplace));
	  	print " INFO: Compare old and new phonebook file versions." . PHP_EOL . " INFO: old version: " . $oldphonebhash . PHP_EOL . " INFO: new version: " . $newphonebhash . PHP_EOL;
	  	if($oldphonebhash === $newphonebhash)
      	{
      	print " INFO: Same versions ==> No changes in phonebook or images" . PHP_EOL . " EXIT: No need to upload phonebook to the FRITZ!Box.". PHP_EOL;
      	return 0;
      	}
      	else
      	print " INFO: Different versions ==> Changes in phonebook." . PHP_EOL . " INFO: Changes dedected! Continue with upload." . PHP_EOL;
      }
	  else
      return 0;  
    }
    // now we upload the photo jpgs first being stored in the
    // temp directory.

    // perform an ftps-connection to copy over the photos to a specified directory
    $ftp_server = $this->config['fritzbox_ip_ftp'];
    $conn_id = ftp_ssl_connect($ftp_server);
    if($conn_id == false)
    {
      print " WARNING: Secure connection to FTP-server '" . $ftp_server . "' failed, retrying without SSL." . PHP_EOL;
      $conn_id = ftp_connect($ftp_server);
    }

    if($conn_id != false)
    {
      ftp_set_option($conn_id, FTP_TIMEOUT_SEC, 60);
      $login_result = ftp_login($conn_id, $this->config['fritzbox_user'], $this->config['fritzbox_pw']);
      if($login_result === true)
      {
        ftp_pasv($conn_id, true);

        // create remote photo path on FRITZ!Box if it doesn't exist
        $remote_path = $this->config['usb_disk'] . "/FRITZ/fonpix";
        $all_existing_files = ftp_nlist($conn_id, $remote_path);
        if($all_existing_files == false)
        {
          ftp_mkdir($conn_id, $remote_path);
          $all_existing_files = array();
        }

        // now iterate through all jpg files in tempdir and upload them if necessary
        $dir = new DirectoryIterator($this->tmpdir);
        foreach($dir as $fileinfo)
        {
          if(!$fileinfo->isDot())
          {
            if($fileinfo->getExtension() == "jpg")
            {
              $file = $fileinfo->getFilename();

              print " FTP-Upload '" . $file . "'...";
              if(!in_array($remote_path . "/" . $file, $all_existing_files))
              {
                if(!ftp_put($conn_id, $remote_path . "/" . $file, $fileinfo->getPathname(), FTP_BINARY))
                {
                  // retry when a fault occurs.
                  print " retrying... ";
                  $conn_id = ftp_ssl_connect($ftp_server);
                  if($conn_id == false)
                  {
                    print " WARNING: Secure re-connection to FTP-server '" . $ftp_server . "' failed, retrying without SSL." . PHP_EOL;
                    $conn_id = ftp_connect($ftp_server);
                  }

                  if($conn_id == false)
                  {
                    print " ERROR: couldn't re-connect to FTP server '" . $ftp_server . "', abortіng." . PHP_EOL;
                    break;
                  }

                  $login_result = ftp_login($conn_id, $this->config['fritzbox_user'], $this->config['fritzbox_pw']);
                  if($login_result === false)
                  {
                    print " ERROR: couldn't re-login to FTP-server '" . $ftp_server . "' with provided username/password settings." . PHP_EOL;
                    break;
                  }

                  ftp_pasv($conn_id, true);
                  if(!ftp_put($conn_id, $remote_path . "/" . $file, $fileinfo->getPathname(), FTP_BINARY))
                    print " ERROR: while uploading file " . $fileinfo->getFilename() . PHP_EOL;
                  else
                    print " ok." . PHP_EOL;
                }
                else
                  print " ok." . PHP_EOL;

                // cleanup old files
                foreach($all_existing_files as $existing_file)
                {
                  if(strpos($existing_file, $remote_path . "/" . substr($file, 0, -10)) !== false)
                  {
                    print " FTP-Delete: " . $existing_file . PHP_EOL;
                    ftp_delete($conn_id, $remote_path . "/" . basename($existing_file));
                  }
                }
              }
              else
                print " already exists." . PHP_EOL;
            }
          }
        }
      }
      else
        print " ERROR: couldn't login to FTP-server '" . $ftp_server . "' with provided username/password settings." . PHP_EOL;

      // close ftp connection
      ftp_close($conn_id);
    }
    else
      print " ERROR: couldn't connect to FTP server '" . $ftp_server . "'." . PHP_EOL;
    
    // lets post the phonebook xml to the FRITZ!Box
    print " Uploading Phonebook XML to " . $this->config['fritzbox_ip'] . PHP_EOL;
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

      $raw_result = $fritz->doPostFile($formfields, $filefileds); // send the command
      $msg = $this->_parse_fb_result($raw_result);
      unset($fritz); // destroy the object to log out

      print "  FRITZ!Box returned message: '" . $msg . "'" . PHP_EOL;
    }
    catch(Exception $e)
    {
      print "  ERROR: " . $e->getMessage() . PHP_EOL; // show the error message in anything failed
    }
  }
}
