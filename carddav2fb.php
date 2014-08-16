<?php
/**
 * CardDAV to FritzBox! XML (automatic upload)
 * inspired by http://www.wehavemorefun.de/fritzbox/Hochladen_eines_MySQL-Telefonbuchs
 * 
 * Requirements: 
 *   php5, php-curl (Debian/Ubuntu install shortcut: sudo apt-get install php5-cli php5-curl)
 * 
 * used libraries: 
 *  *  vCard-parser <https://github.com/nuovo/vCard-parser> (LICNECE: unknown)
 *  *  CardDAV-PHP <https://github.com/graviox/CardDAV-PHP>(LICENCE: AGPLv3)
 *  *  fritzbox_api_php <https://github.com/carlos22/fritzbox_api_php> (LICENCE: CC-by-SA 3.0)
 * 
 * LICENCE (of this file): MIT
 * 
 * Autor: Karl Glatz
 * Modified by Martin Rost
 */
error_reporting(E_ALL);
setlocale(LC_ALL, 'de_DE.UTF8');

require_once('lib/CardDAV-PHP/carddav.php');
require_once('lib/vCard-parser/vCard.php');
require_once('lib/fritzbox_api_php/fritzbox_api.class.php');

if ($argc == 2) {
	$config_file_name = $argv[1];
} else {
	$config_file_name = 'config.php';
}

if(is_file($config_file_name)) {
	require($config_file_name);
} else {
	print 'ERROR: No '.$config_file_name.' found, please take a look at config.example.php and create a '.$config_file_name.' file!'.PHP_EOL;
	exit(1);
}

// ---------------------------------------------

// MAIN

$client = new CardDAV2FB($config);


// read vcards from webdav
print 'Get all entries from CardDAV server(s)... ';
$client->get_carddav_entries();
print 'Done.'.PHP_EOL;

flush(); // in case this script runs by php-cgi

// transform them to a fritzbox compatible xml file
print 'Transform to FritzBox XML File... ';
$client->build_fb_xml();
print 'Done.'.PHP_EOL;

flush(); // in case this script runs by php-cgi

// upload the xml file to the fritz box (CAUTION: this will overwrite all current entries in the phonebook!!)
print 'Upload to fritzbox at '.$config['fritzbox_ip'].'...';
$ul_msg = $client->upload_to_fb();
print 'Done.'.PHP_EOL;
print 'FritzBox: '.$ul_msg.PHP_EOL;

flush(); // in case this script runs by php-cgi


class CardDAV2FB {
	
	protected $entries = array();
	protected $fbxml = "";
	protected $config = null;
	
	public function __construct($config) {
		$this->config = $config;
	}
		

	public function get_carddav_entries() {
		$entries = array();

		foreach($this->config['carddav'] as $conf) {
			$carddav = new carddav_backend($conf['url']);
			$carddav->set_auth($conf['user'], $conf['pw']);
			$xmldata =  $carddav->get();
			
			// read raw_vcard data from xml response
			$raw_vcards = array();
			$xmlvcard = new SimpleXMLElement($xmldata);

			foreach($xmlvcard->element as $vcard_element)
			{
				$id = $vcard_element->id->__toString();
				$value = (string)$vcard_element->vcard->__toString();
				$raw_vcards[$id] = $value;
			}

			// parse raw_vcards
			$result = array();
			foreach($raw_vcards as $v) {
				$vcard_obj = new vCard(false, $v);
				
				// name
				$name_arr = $vcard_obj->n[0];

				$name = $this->_concat($name_arr['Prefixes'],$this->_concat($this->_concat($name_arr['FirstName'],$name_arr['AdditionalNames']),$name_arr['LastName']));

				// if name is empty we take org instead
				if(empty($name))
				{
					$name_arr = $vcard_obj->org[0];
					$name = $name_arr['Name'];
				}

				if ($vcard_obj->photo) {
					$photo = str_replace(array('&',' ','ä','ö','ü','Ä','Ö','Ü','ß','á','à','ó','ò','ú','ù'),
							     array('_','_','ae','oe','ue','Ae','Oe','Ue','ss','a','a','o','o','u','u'),$name);
				} else {
					$photo = '';
				}
				
				$phone_no = array();
				if ($vcard_obj->categories) {
					$categories = $vcard_obj->categories[0];
				} else {
					$categories = array('');
				}
				if (in_array($this->config['group_vip'],$categories)) {
					$vip = 1;
				}
				else {
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
						if (empty($t['Type'])) {
							$type = "mobile";
							$phone_number = $t;
						}
						else {
							$phone_number = $t['Value'];
							$typearr_lower = unserialize(strtolower(serialize($t['Type'])));
		                                        if (in_array("work", $typearr_lower)) {
		                                                $type = "work";
		                                        }
							elseif (in_array("cell", $typearr_lower)) {
								$type = "mobile";
							}
							elseif (in_array("home", $typearr_lower)) {
								$type = "home";
								$prio = 1;
							}
							else {
								continue;
							}
						}

						$phone_no[] =  array("type"=>$type, "prio"=>$prio, "value" => $this->_clear_phone_number($phone_number));
					}
					$entries[] = array("realName" => $name, "telephony" => $phone_no, "vip" => $vip, "photo" => $photo);
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
		
                // create FB XML
                $root = new SimpleXMLElement('<?xml version="1.0" encoding="iso-8859-1"?><phonebooks><phonebook></phonebook></phonebooks>');
                $pb = $root->phonebook;
		$pb->addAttribute("name",$this->config['phonebook_name']);
		
		foreach($this->entries as $entry) {
			
				$contact = $pb->addChild("contact");
				$contact->addChild("category", $entry['vip']);
				$person = $contact->addChild("person");
				$person->addChild("realName", $this->_convert_text($entry['realName']));
				if (($entry['photo']) and (array_key_exists('usb_disk',$this->config))) {
					$person->addChild("imageURL","file:///var/media/ftp/".$this->config['usb_disk']."/FRITZ/fonpix/".$entry['photo'].".jpg");
				}
				$telephony = $contact->addChild("telephony");
			
				$id = 0;	
				foreach($entry['telephony'] as $tel) {
					$num = $telephony->addChild("number", $tel['value']);
					$num->addAttribute("type", $tel['type']);
					$num->addAttribute("vanity","");
					$num->addAttribute("prio", $tel['prio']);
					$num->addAttribute("id", $id);
					$id++;
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
			return $text1." ".$text2;
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


		if (array_key_exists('output_file',$this->config)) {
			$output = fopen($this->config['output_file'], 'w');
			if ($output) {
				fwrite($output, $this->fbxml);
				fclose($output);
			}
			return 0;
		};

		$msg = "";
		try
		{
		  $fritz = new fritzbox_api($this->config['fritzbox_pw'],$this->config['fritzbox_user'],$this->config['fritzbox_ip']);
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
		  $fritz = null;                     					// destroy the object to log out
		}
		catch (Exception $e)
		{
		  print $e->getMessage();     // show the error message in anything failed
		  print PHP_EOL;
		}
		return $msg;
	}
}

?>
