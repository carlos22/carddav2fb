<?php

namespace Andig\FritzBox;

use Andig;
use \SimpleXMLElement;
use \stdClass;

class Converter
{
    private $config;
    private $configImagePath;

    /** @var mixed */
    private $card;

    /** @var SimpleXMLElement */
    private $contact;

    private $uniqueDials = [];
    private $phoneSort = [];

    public function __construct(array $config)
    {
        $this->config    = $config['conversions'];
        $this->configImagePath = @$config['phonebook']['imagepath'];
        $this->phoneSort = $this->getPhoneTypesSortOrder();
    }

    /**
     * Convert Vcard to FritzBox XML
     * All conversion steps operate on $this->contact
     *
     * @param stdClass $card
     * @return SimpleXMLElement|null
     */
    public function convert(stdClass $card)
    {
        $numbers  = $this->getPhoneNumbers($card);  // get array of prequalified phone numbers
        $adresses = $this->getEmailAdresses($card); // get array of prequalified email adresses

        if (!count($numbers)) {
            return null;
        }

        $this->contact = new SimpleXMLElement('<contact />');
        $this->contact->addChild('carddav_uid', $card->uid);    // reference for image upload

        $this->addVip($card);
        $this->addPhone($numbers);

        // add eMail
        if (count($adresses)) {
            $this->addEmail($adresses);
        }

        // add Person
        $person = $this->contact->addChild('person');
        $realName = htmlspecialchars($this->getProperty($card, 'realName'));
        $person->addChild('realName', $realName);

        // add photo
        if (isset($card->rawPhoto) && isset($card->imageURL)) {
            if (isset($this->configImagePath)) {
                $person->addChild('imageURL', $card->imageURL);
            }
        }

        return $this->contact;
    }

    /**
     * Return a simple array depending on the order of phonetype conversions
     * whose order should determine the sorting of the telephone numbers
     *
     * @return array
     */
    private function getPhoneTypesSortOrder(): array
    {
        $seqArr = array_values(array_map('strtolower', $this->config['phoneTypes']));
        $seqArr[] = 'other';                               // ensures that the default value is included
        return array_unique($seqArr);                      // deletes duplicates
    }

    private function addVip(stdClass $card)
    {
        $vipCategories = $this->config['vip'] ?? [];

        if (Andig\filtersMatch($card, $vipCategories)) {
            $this->contact->addChild('category', '1');
        }
    }

    private function addPhone(array $numbers)
    {
        $telephony = $this->contact->addChild('telephony');
        $phoneCounter = 0;

        foreach ($numbers as $number) {
            $phone = $telephony->addChild('number', $number['number']);
            $phone->addAttribute('id', (string)$phoneCounter);

            foreach (['type', 'quickdial', 'vanity'] as $attribute) {
                if (isset($number[$attribute])) {
                    // pref is mapped to prio
                    $targetAttribute = $attribute == 'pref' ? 'prio' : $attribute;
                    $phone->addAttribute($targetAttribute, $number[$attribute]);
                }
            }

            // not more than nine phone numbers per contact
            if (++$phoneCounter == 9) {
                break;
            }
        }
    }

    private function addEmail(array $addresses)
    {
        $services = $this->contact->addChild('services');
        $eMailCounter = 0;

        foreach ($addresses as $address) {
            $email = $services->addChild('email', $address['email']);
            $email->addAttribute('id', (string)$eMailCounter);

            if (isset($address['classifier'])) {
                $email->addAttribute('classifier', $address['classifier']);
            }

            $eMailCounter++;
        }
    }

    /**
     * Return an array of prequalified phone numbers. This is neccesseary to
     * handle the maximum of nine phone numbers per FRITZ!Box phonebook contacts
     *
     * @param stdClass $card
     * @return array
     */
    private function getPhoneNumbers(stdClass $card): array
    {
        if (!isset($card->phone)) {
            return [];
        }

        $phoneNumbers = [];

        $replaceCharacters = $this->config['phoneReplaceCharacters'] ?? [];
        $phoneTypes = $this->config['phoneTypes'] ?? [];

        foreach ($card->phone as $numberType => $numbers) {
            $addNumber = []; // TODO: this catches a small bug in the logic below

            foreach ($numbers as $number) {
                $addNumber = [];

                if (count($replaceCharacters)) {
                    $number = str_replace("\xc2\xa0", "\x20", $number);   // delete the wrong ampersand conversion
                    $number = strtr($number, $replaceCharacters);
                    $number = trim(preg_replace('/\s+/', ' ', $number));
                }

                $addNumber['number'] = $number;
                $type = 'other';
                $numberType = strtolower($numberType);

                if (stripos($numberType, 'fax') !== false) {
                    $type = 'fax_work';
                } else {
                    foreach ($phoneTypes as $type => $value) {
                        if (stripos($numberType, $type) !== false) {
                            $type = $value;
                            break;
                        }
                    }
                }
                $addNumber['type'] = $type;
            }

            if (strpos($numberType, 'pref') !== false) {
                $addNumber['pref'] = 1;
            }

            // add quick dial number; Fritz!Box will add the prefix **7 automatically
            if (isset($card->xquickdial)) {
                if (!in_array($card->xquickdial, $this->uniqueDials)) {    // quick dial number really unique?
                    if (strpos($numberType, 'pref') !== false) {
                        $addNumber['quickdial'] = $card->xquickdial;
                        $this->uniqueDials[] = $card->xquickdial;          // keep quick dial number for cross check
                        unset($card->xquickdial);                          // flush used quick dial number
                    }
                } else {
                    $format = "The quick dial number >%s< has been assigned more than once (%s)!";
                    error_log(sprintf($format, $card->xquickdial, $number));
                }
            }

            // add vanity number; Fritz!Box will add the prefix **8 automatically
            if (isset($card->xvanity)) {
                if (!in_array($card->xvanity, $this->uniqueDials)) {       // vanity string really unique?
                    if (strpos($numberType, 'pref') !== false) {
                        $addNumber['vanity'] = $card->xvanity;
                        $this->uniqueDials[] = $card->xvanity;             // keep vanity string for cross check
                        unset($card->xvanity);                             // flush used vanity number
                    }
                } else {
                    $format = "The vanity string >%s< has been assigned more than once (%s)!";
                    error_log(sprintf($format, $card->xvanity, $number));
                }
            }

            $phoneNumbers[] = $addNumber;
        }

        // sort phone numbers
        if (count($phoneNumbers) > 1) {
            usort($phoneNumbers, function ($a, $b) {
                $idx1 = array_search($a['type'], $this->phoneSort, true);
                $idx2 = array_search($b['type'], $this->phoneSort, true);
                if ($idx1 == $idx2) {
                    return ($a['number'] > $b['number']) ? 1 : -1;
                } else {
                    return ($idx1 > $idx2) ? 1 : -1;
                }
            });
        }

        return $phoneNumbers;
    }

    /**
     * Return an array of prequalified email adresses. There is no limitation
     * for the amount of email adresses in FRITZ!Box phonebook contacts.
     *
     * @param stdClass $card
     * @return array
     */
    private function getEmailAdresses(stdClass $card): array
    {
        if (!isset($card->email)) {
            return [];
        }

        $mailAdresses = [];
        $emailTypes = $this->config['emailTypes'] ?? [];

        foreach ($card->email as $emailType => $addresses) {
            foreach ($addresses as $idx => $addr) {
                $addAddress = [
                    'id' => $idx,
                    'email' => $addr,
                ];

                foreach ($emailTypes as $type => $value) {
                    if (strpos($emailType, $type) !== false) {
                        $addAddress['classifier'] = $value;
                        break;
                    }
                }

                $mailAdresses[] = $addAddress;
            }
        }

        return $mailAdresses;
    }

    /**
     * Return class proeprty with applied conversion rules
     *
     * @param stdClass $card
     * @param string $property
     * @return string
     */
    private function getProperty(stdClass $card, string $property): string
    {
        if (null === ($rules = @$this->config[$property])) {
            throw new \Exception("Missing conversion definition for `$property`");
        }

        foreach ($rules as $rule) {
            // parse rule into tokens
            $token_format = '/{([^}]+)}/';
            preg_match_all($token_format, $rule, $tokens);

            if (!count($tokens)) {
                throw new \Exception("Invalid conversion definition for `$property`");
            }

            $replacements = [];

            // check card for tokens
            foreach ($tokens[1] as $idx => $token) {
                if (isset($card->$token) && $card->$token) {
                    $replacements[$token] = $card->$token;
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
