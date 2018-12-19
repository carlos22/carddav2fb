<?php

namespace Andig\FritzBox;

use Andig;
use \SimpleXMLElement;

class Converter
{
    private $config;
    private $imagePath;

    public function __construct($config)
    {
        $this->config    = $config['conversions'];
        $this->imagePath = $config['phonebook']['imagepath'] ?? NULL;
    }

    public function convert($card): SimpleXMLElement
    {
        $this->card = $card;

        $this->contact = new SimpleXMLElement('<contact />');

        $this->contact->addChild('carddav_uid',$this->card->uid);    // reference for image upload
        
        $this->addVip();

        // add Person
        $person = $this->contact->addChild('person');
        $name = htmlspecialchars($this->getProperty('realName'));
        $person->addChild('realName', $name);
        
        // add photo
        if (isset($this->card->rawPhoto)) {
            if (isset($this->imagePath)) {
                $person->addChild('imageURL', $this->imagePath . $this->card->uid . '.jpg');
            }
        }
        
        $this->addPhone();
        
        $this->addEmail();

        return $this->contact;
    }

    private function addVip()
    {
        $vipCategories = $this->config['vip'] ?? array();

        if (Andig\filtersMatch($this->card, $vipCategories)) {
            $this->contact->addChild('category', 1);
        }
    }

    private function addPhone()
    {
        // <telephony>
        //  <number type="work" vanity="" prio="1" id="0">+490358179022</number>
        //  <number type="work" vanity="" prio="0" id="1">+400746653254</number></telephony>

        $replaceCharacters = $this->config['phoneReplaceCharacters'] ?? array();
        $phoneTypes = $this->config['phoneTypes'] ?? array();

        if (isset($this->card->phone)) {
            $telephony = $this->contact->addChild('telephony');
            $idnum = -1;
            foreach ($this->card->phone as $numberType => $numbers) {
                foreach ($numbers as $idx => $number) {
                    $idnum++;
                    if (count($replaceCharacters)) {
                        $number = str_replace("\xc2\xa0", "\x20", $number);   // delete the wrong ampersand conversion
                        $number = strtr($number, $replaceCharacters);
                        $number = trim(preg_replace('/\s+/', ' ', $number));
                    }

                    $phone = $telephony->addChild('number', $number);
                    $phone->addAttribute('id', $idnum);

                    $type = 'other';
                    $numberType = strtolower($numberType);

                    if (stripos($numberType, 'fax') !== false) {
                        $type = 'fax_work';
                    }
                    else {
                        foreach ($phoneTypes as $type => $value) {
                            if (stripos($numberType, $type) !== false) {
                                $type = $value;
                                break;
                            }
                        }
                    }

                    $phone->addAttribute('type', $type);

                }    
                if (strpos($numberType, 'pref') !== false) {
                    $phone->addAttribute('prio', 1);
                }
            }
        }
    }

    private function addEmail()
    {
        // <services>
        //  <email classifier="work" id="0">no-reply@dummy.de</email>
        //  <email classifier="work" id="1">no-reply@dummy.de</email></

        $emailTypes = $this->config['emailTypes'] ?? array();

        if (isset($this->card->email)) {
            $services = $this->contact->addChild('services');
            foreach ($this->card->email as $emailType => $addresses) {
                foreach ($addresses as $idx => $addr) {
                    $email = $services->addChild('email', $addr);
                    $email->addAttribute('id', $idx);

                    foreach ($emailTypes as $type => $value) {
                        if (strpos($emailType, $type) !== false) {
                            $email->addAttribute('classifier', $value);
                            break;
                        }
                    }
                }
            }
        }
    }

    private function getProperty(string $property): string
    {
        if (null === ($rules = $this->config[$property] ?? null)) {
            throw new \Exception("Missing conversion definition for `$property`");
        }

        foreach ($rules as $rule) {
            // parse rule into tokens
            $token_format = '/{([^}]+)}/';
            preg_match_all($token_format, $rule, $tokens);

            if (!count($tokens)) {
                throw new \Exception("Invalid conversion definition for `$property`");
            }

            // print_r($tokens);
            $replacements = [];

            // check card for tokens
            foreach ($tokens[1] as $idx => $token) {
                // echo $idx.PHP_EOL;
                if (isset($this->card->$token) && $this->card->$token) {
                    // echo $tokens[0][$idx].PHP_EOL;
                    $replacements[$token] = $this->card->$token;
                    // echo $this->card->$token;
                }
            }

            // check if all tokens found
            if (count($replacements) !== count($tokens[0])) {
                continue;
            }

            // replace
            return preg_replace_callback($token_format, function ($match) use ($replacements) {
                $token = $match[1];
                return $replacements[$token];
            }, $rule);
        }

        error_log("No data for conversion `$property`");

        return '';
    }
}