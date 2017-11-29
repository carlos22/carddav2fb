<?php

namespace Andig\FritzBox;

use Andig;
use \SimpleXMLElement;

class Converter
{
    private $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function convert($card): SimpleXMLElement
    {
        $this->card = $card;

        // $contact = $xml->addChild('contact');
        $this->contact = new SimpleXMLElement('<contact />');

        $this->addVip();

        $person = $this->contact->addChild('person');
        $name = htmlspecialchars($this->getProperty('realName'));
        $person->addChild('realName', $name);
        // $person->addChild('ImageURL');

        $this->addPhone();
        $this->addEmail();

        $person = $this->contact->addChild('setup');

        // print_r($this->contact);
        // echo($this->contact->asXML().PHP_EOL);

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
        // 	<number type="work" vanity="" prio="1" id="0">+490358179022</number>
        // 	<number type="work" vanity="" prio="0" id="1">+400746653254</number></telephony>

        $telephony = $this->contact->addChild('telephony');

        $replaceCharacters = $this->config['phoneReplaceCharacters'] ?? array();
        $phoneTypes = $this->config['phoneTypes'] ?? array();

        if (isset($this->card->phone)) {
            foreach ($this->card->phone as $numberType => $numbers) {
                foreach ($numbers as $idx => $number) {
                    if (count($replaceCharacters)) {
                        $number = strtr($number, $replaceCharacters);
                        $number = trim(preg_replace('/\s+/', ' ', $number));
                    }

                    $phone = $telephony->addChild('number', $number);
                    $phone->addAttribute('id', $idx);

                    $type = 'other';

                    foreach ($phoneTypes as $type => $value) {
                        if (strpos($numberType, $type) !== false) {
                            $type = $value;
                            if (strpos($numberType, 'FAX') !== false) {
                                $type = 'fax_' . $type;
                            }

                            break;
                        }
                    }

                    $phone->addAttribute('type', $type);

                    if (strpos($numberType, 'pref') !== false) {
                        $phone->addAttribute('prio', 1);
                    }

                    // $phone->addAttribute('vanity', '');
                }
            }
        }
    }

    private function addEmail()
    {
        // <services>
        // 	<email classifier="work" id="0">KTS.Michaelis.Hannover@evlka.de</email>
        // 	<email classifier="work" id="1">Kindertagesstaette@michaelis-hannover.de</email></

        $services = $this->contact->addChild('services');
        $emailTypes = $this->config['emailTypes'] ?? array();

        if (isset($this->card->email)) {
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

                    // $email->addAttribute('vanity', '');
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
