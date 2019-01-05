<?php

namespace Andig;

use Andig\CardDav\Backend;
use Andig\Vcard\Parser;
use Andig\FritzBox\Converter;
use Andig\FritzBox\Api;
use \SimpleXMLElement;

/**
 * Initialize backend from configuration
 *
 * @param array $config
 * @return Backend
 */
function backendProvider(array $config): Backend
{
    $server = $config['server'] ?? $config;
    $authentication = $server['authentication'] ?? null;

    $backend = new Backend();
    $backend->setUrl($server['url']);
    $backend->setAuth($server['user'], $server['password'], $authentication);

    return $backend;
}

/**
 * Download vcards from CardDAV server
 *
 * @param Backend $backend
 * @param callable $callback
 * @return array
 */
function download(Backend $backend, $substitutes, callable $callback=null): array
{
    $backend->setProgress($callback);
    $backend->setSubstitutes($substitutes);
    return $backend->getVcards();
}

/**
 * upload image files via ftp to the fritzbox fonpix directory
 *
 * @param $vcards     array     downloaded vCards
 * @param $config     array
 * @return                      number of transfered files
 */
function uploadImages(array $vcards, $config)
{
    $options = array('ftp' => array('overwrite' => true));
    $context = stream_context_create($options);
    $i = 0;

    foreach ($vcards as $vcard) {
        if (isset($vcard->rawPhoto)) {                                     // skip all other vCards
            if (preg_match("/JPEG/", strtoupper($vcard->photoData))) {     // Fritz!Box only accept jpg-files
                $imgFile = imagecreatefromstring($vcard->rawPhoto);
                if ($imgFile !== false) {
                    $ftp_destination = sprintf('ftp://%1$s:%2$s@%3$s/%4$s/%5$s.jpg',
                        $config['user'],
                        $config['password'],
                        $config['url'],
                        $config['fonpix'],
                        $vcard->uid
                    );
                    ob_start();
                    imagejpeg($imgFile, null);
                    $contents = ob_get_clean();
                    if (@file_put_contents($ftp_destination, $contents, 0, $context) !== false) {
                        $i++;
                    }
                    else {
                        unset($vcard->rawPhoto);                           // no wrong link will set in phonebook
                        error_log(sprintf("ftp access denied for %s.jpg to %s!", $vcard->uid, $config['fonpix']));
                    }
                    imagedestroy($imgFile);
                }
            }
        }
    }
    return $i;
}

/**
 * Dissolve the groups of iCloud contacts
 *
 * @param array $cards
 * @return array
 */
function dissolveGroups (array $vcards): array
{
    $groups = [];

    foreach ($vcards as $key => $vcard) {          // separate iCloud groups
        if (isset($vcard->xabsmember)) {
            $groups[$vcard->fullname] = $vcard->xabsmember;
            unset($vcards[$key]);
            continue;
        }
    }
    $vcards = array_values($vcards);
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

/**
 * Filter included/excluded vcards
 *
 * @param array $cards
 * @param array $filters
 * @return array
 */
function filter(array $cards, array $filters): array
{
    // include selected
    $includeFilter = $filters['include'] ?? [];

    if (countFilters($includeFilter)) {
        $step1 = [];

        foreach ($cards as $card) {
            if (filtersMatch($card, $includeFilter)) {
                $step1[] = $card;
            }
        }
    }
    else {
        // filter defined but empty sub-rules?
        if (count($includeFilter)) {
            error_log('Include filter empty- including all cards');
        }

        // include all by default
        $step1 = $cards;
    }

    $excludeFilter = $filters['exclude'] ?? [];
    if (!count($excludeFilter)) {
        return $step1;
    }

    $step2 = [];
    foreach ($step1 as $card) {
        if (!filtersMatch($card, $excludeFilter)) {
            $step2[] = $card;
        }
    }

    return $step2;
}

/**
 * Count populated filter rules
 *
 * @param array $filters
 * @return int
 */
function countFilters(array $filters): int
{
    $filterCount = 0;

    foreach ($filters as $key => $value) {
        if (is_array($value)) {
            $filterCount += count($value);
        }
    }

    return $filterCount;
}

/**
 * Check a list of filters against a card
 *
 * @param [type] $card
 * @param array $filters
 * @return bool
 */
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

/**
 * Check a filter against a single attribute
 *
 * @param [type] $attribute
 * @param [type] $filterValues
 * @return bool
 */
function filterMatches($attribute, $filterValues): bool
{
    if (!is_array($filterValues)) {
        $filterValues = array($filterMatches);
    }

    foreach ($filterValues as $filter) {
        if (is_array($attribute)) {
            // check if any attribute matches
            foreach ($attribute as $childAttribute) {
                if ($childAttribute === $filter) {
                    return true;
                }
            }
        } else {
            // check if simple attribute matches
            if ($attribute === $filter) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Export cards to fritzbox xml
 *
 * @param array $cards
 * @param array $conversions
 * @return SimpleXMLElement
 */
function export(array $cards, array $conversions): SimpleXMLElement
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
    $root->addAttribute('name', $conversions['phonebook']['name']);

    $converter = new Converter($conversions);

    foreach ($cards as $card) {
        $contacts = $converter->convert($card);
        foreach ($contacts as $contact) {
            xml_adopt($root, $contact);
        }
    }
    return $xml;
}

/**
 * Attach xml element to parent
 * https://stackoverflow.com/questions/4778865/php-simplexml-addchild-with-another-simplexmlelement
 *
 * @param SimpleXMLElement $to
 * @param SimpleXMLElement $from
 * @return void
 */
function xml_adopt(SimpleXMLElement $to, SimpleXMLElement $from)
{
    $toDom = dom_import_simplexml($to);
    $fromDom = dom_import_simplexml($from);
    $toDom->appendChild($toDom->ownerDocument->importNode($fromDom, true));
}

/**
 * Upload cards to fritzbox
 *
 * @param string $xml
 * @param array $config
 * @return void
 */
function upload(string $xml, $config)
{
    $fritzbox = $config['fritzbox'];

    $fritz = new Api($fritzbox['url'], $fritzbox['user'], $fritzbox['password']);

    $formfields = array(
        'PhonebookId' => $config['phonebook']['id']
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
