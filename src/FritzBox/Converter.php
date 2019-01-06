<?php

namespace Andig\FritzBox;

use Andig;
use \SimpleXMLElement;

class Converter

{
    private $config;
    private $imagePath;
    private $numbers;
    private $adresses;
    private $uniqueDials = [];
    private $phoneSort = [];

    public function __construct($config)
    {
        $this->config    = $config['conversions'];
        $this->imagePath = $config['phonebook']['imagepath'] ?? NULL;
        $this->phoneSort = $this->getPhoneTypesSortOrder();
    }

    public function convert($card)
    {
        $this->card = $card;
        $contacts = [];

        $this->numbers  = $this->getPhoneNumbers();                      // get array of prequalified phone numbers
        $this->adresses = $this->getEmailAdresses();                     // get array of prequalified email adresses

        while ((count($this->numbers)) || (count($this->adresses))) {
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
            // add Phone
            if (count($this->numbers)) {
                $this->addPhone();
            }
            // add eMail
            if (count($this->adresses)) {
                $this->addEmail();
            }
            $contacts[] = $this->contact;
        }
        return $contacts;
    }

    /**
     * returns a simple array depending on the order of phonetype conversions
     * whose order should determine the sorting of the telephone numbers
     */
    private function getPhoneTypesSortOrder()
    {
        $seqArr = array_values(array_map('strtolower', $this->config['phoneTypes']));
        $seqArr[] = 'other';                               // ensures that the default value is included
        return array_unique($seqArr);                      // deletes duplicates
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
        $telephony = $this->contact->addChild('telephony');
        $phoneCounter = 0;
        while (count($this->numbers)) {
            $phone = $telephony->addChild('number', $this->numbers[0]['number']);
            $phone->addAttribute('id', $phoneCounter);
            if (isset($this->numbers[0]['type'])) {
                $phone->addAttribute('type', $this->numbers[0]['type']);
            }
            if (isset($this->numbers[0]['pref'])) {
                $phone->addAttribute('prio', $this->numbers[0]['pref']);
            }
            if (isset($this->numbers[0]['quickdial'])) {
                $phone->addAttribute('quickdial', $this->numbers[0]['quickdial']);
            }
            if (isset($this->numbers[0]['vanity'])) {
                $phone->addAttribute('vanity', $this->numbers[0]['vanity']);
            }
            array_shift($this->numbers);
            $phoneCounter++;
            // not more than nine phone numbers per contact
            if ($phoneCounter == 9) {
                break;
            }
        }
    }

    private function addEmail()
    {
        $services = $this->contact->addChild('services');
        $eMailCounter = 0;
        while (count($this->adresses)) {
            $email = $services->addChild('email', $this->adresses[0]['email']);
            $email->addAttribute('id', $eMailCounter);
            if (isset($this->adresses[0]['classifier'])) {
                $email->addAttribute('classifier', $this->adresses[0]['classifier']);
            }
            array_shift($this->adresses);
            $eMailCounter++;
        }
    }

    /**
     * delivers an array of prequalified phone numbers. This is neccesseary to
     * handle the maximum of nine phone numbers per FRITZ!Box phonebook contacts
     */
    private function getPhoneNumbers()
    {
        $phoneNumbers = [];

        $replaceCharacters = $this->config['phoneReplaceCharacters'] ?? array();
        $phoneTypes = $this->config['phoneTypes'] ?? array();
        if (isset($this->card->phone)) {
            $idnum = -1;
            foreach ($this->card->phone as $numberType => $numbers) {
                foreach ($numbers as $number) {
                    $idnum++;
                    if (count($replaceCharacters)) {
                        $number = str_replace("\xc2\xa0", "\x20", $number);   // delete the wrong ampersand conversion
                        $number = strtr($number, $replaceCharacters);
                        $number = trim(preg_replace('/\s+/', ' ', $number));
                    }
                    $phoneNumbers[$idnum]['number'] = $number;
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
                    $phoneNumbers[$idnum]['type'] = $type;
                }
                if (strpos($numberType, 'pref') !== false) {
                    $phoneNumbers[$idnum]['pref'] = 1;
                }
                // add quick dial number; Fritz!Box will add the prefix **7 automatically
                if (isset($this->card->xquickdial)) {
                    if (!in_array($this->card->xquickdial, $this->uniqueDials)) {    // quick dial number really unique?
                        if (strpos($numberType, 'pref') !== false) {
                            $phoneNumbers[$idnum]['quickdial'] = $this->card->xquickdial;
                            $this->uniqueDials[] = $this->card->xquickdial;          // keep quick dial number for cross check
                            unset($this->card->xquickdial);                          // flush used quick dial number
                        }
                    }
                    else {
                        $format = "The quick dial number >%s< has been assigned more than once (%s)!";
                        error_log(sprintf($format, $this->card->xquickdial, $number));
                    }
                }
                // add vanity number; Fritz!Box will add the prefix **8 automatically
                if (isset($this->card->xvanity)) {
                    if (!in_array($this->card->xvanity, $this->uniqueDials)) {       // vanity string really unique?
                        if (strpos($numberType, 'pref') !== false) {
                            $phoneNumbers[$idnum]['vanity'] = $this->card->xvanity;
                            $this->uniqueDials[] = $this->card->xvanity;             // keep vanity string for cross check
                            unset($this->card->xvanity);                             // flush used vanity number
                        }
                    }
                    else {
                        $format = "The vanity string >%s< has been assigned more than once (%s)!";
                        error_log(sprintf($format, $this->card->xvanity, $number));
                    }
                }
            }
        }
        if (count($phoneNumbers) > 1) {
            usort($phoneNumbers, function($a, $b) {
                $idx1 = array_search($a['type'], $this->phoneSort, true);
                $idx2 = array_search($b['type'], $this->phoneSort, true);
                if ($idx1 == $idx2)
                    return ($a['number'] > $b['number']) ? 1 : -1;
                else
                    return ($idx1 > $idx2) ? 1 : -1;
            });
        }
        return $phoneNumbers;
    }

    /**
     * delivers an array of prequalified email adresses. There is no limitation
     * for the amount of email adresses in FRITZ!Box phonebook contacts.
     */
    private function getEmailAdresses()
    {
        $mailAdresses = [];
        $emailTypes = $this->config['emailTypes'] ?? array();

        if (isset($this->card->email)) {
            foreach ($this->card->email as $emailType => $addresses) {
                foreach ($addresses as $idx => $addr) {
                    $mailAdresses[$idx]['email'] = $addr;
                    $mailAdresses[$idx]['id'] = $idx;
                    foreach ($emailTypes as $type => $value) {
                        if (strpos($emailType, $type) !== false) {
                            $mailAdresses[$idx]['classifier'] = $value;
                            break;
                        }
                    }
                }
            }
        }
        return $mailAdresses;
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