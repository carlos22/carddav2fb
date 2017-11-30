<?php

namespace Andig;

use Andig\CardDav\Backend;
use Andig\Vcard\Parser;
use Andig\FritzBox\Converter;
use Andig\FritzBox\Api;
use \SimpleXMLElement;

function backendProvider(array $config): Backend
{
    $server = $config['server'] ?? $config;

    $backend = new Backend();
    $backend->setUrl($server['url']);
    $backend->setAuth($server['user'], $server['password']);

    return $backend;
}

function download(Backend $backend, callable $callback=null): array
{
    $backend->setProgress($callback);
    return $backend->getVcards();
}

function downloadImages(Backend $backend, array $cards, callable $callback=null): array
{
    foreach ($cards as $card) {
        if (isset($card->photo)) {
            $uri = $card->photo;
            $image = $backend->fetchImage($uri);
            $card->photo_data = utf8_encode($image);

            if (is_callable($callback)) {
                $callback();
            }
        }
    }

    return $cards;
}

function countImages(array $cards): int
{
    $images = 0;

    foreach ($cards as $card) {
        if (isset($card->photo_data)) {
            $images++;
        }
    }

    return $images;
}

function parse(array $cards): array
{
    $vcards = [];
    $groups = [];

    // parse all vcards
    foreach ($cards as $card) {
        $parser = new Parser($card);
        $vcard = $parser->getCardAtIndex(0);

        // separate iCloud groups
        if (isset($vcard->xabsmember)) {
            $groups[$vcard->fullname] = $vcard->xabsmember;
            continue;
        }

        $vcards[] = $vcard;
    }

    // assign group memberships
    foreach ($vcards as $vcard) {
        foreach ($groups as $group => $members) {
            if (in_array($vcard->uid, $members)) {
                if (!isset($vcard->group)) {
                    $vcard->group = array();
                }

                $vcard->group = $group;
                break;
            }
        }
    }

    return $vcards;
}

function filter(array $cards, array $filters): array
{
    $result = [];

    foreach ($cards as $card) {
        if (filtersMatch($card, $filters)) {
            continue;
        }

        $result[] = $card;
    }

    return $result;
}

function filtersMatch($card, array $filters): bool
{
    foreach ($filters as $attribute => $values) {
        if (isset($card->$attribute)) {
            if (filterMatches($card->$attribute, $values)) {
                return true;
            }
        }
    }

    return false;
}

function filterMatches($attribute, $filterValues): bool
{
    if (!is_array($filterValues)) {
        $filterValues = array($filterMatches);
    }

    foreach ($filterValues as $filter) {
        if ($attribute === $filter) {
            return true;
        }
    }

    return false;
}

function export(string $name, array $cards, array $conversions): SimpleXMLElement
{
    $xml = new SimpleXMLElement(
        <<<EOT
<?xml version="1.0" encoding="UTF-8"?>
<phonebooks>
<phonebook />
</phonebooks>
EOT
    );

    $root = $xml->xpath('//phonebook')[0];
    $root->addAttribute('name', $name);

    $converter = new Converter($conversions);

    foreach ($cards as $card) {
        $contact = $converter->convert($card);
        // $root->addChild('contact', $contact);
        xml_adopt($root, $contact);
    }

    return $xml;
}

// https://stackoverflow.com/questions/4778865/php-simplexml-addchild-with-another-simplexmlelement
function xml_adopt(SimpleXMLElement $to, SimpleXMLElement $from)
{
    $toDom = dom_import_simplexml($to);
    $fromDom = dom_import_simplexml($from);
    $toDom->appendChild($toDom->ownerDocument->importNode($fromDom, true));
}


function upload(string $xml, string $url, string $user, string $password, int $phonebook=0)
{
    $fritz = new Api($url, $user, $password, 1);

    $formfields = array(
        'PhonebookId' => $phonebook
    );

    $filefields = array(
        'PhonebookImportFile' => array(
            'type' => 'text/xml',
            'filename' => 'updatepb.xml',
            'content' => $xml,
        )
    );

    $result = $fritz->doPostFile($formfields, $filefields); // send the command

    if (strpos($result, 'Das Telefonbuch der FRITZ!Box wurde wiederhergestellt') === false) {
        throw new \Exception('Upload failed');
    }
}
