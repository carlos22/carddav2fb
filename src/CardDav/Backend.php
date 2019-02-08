<?php

namespace Andig\CardDav;

use Andig\Http\ClientTrait;
use Andig\Vcard\Parser;
use GuzzleHttp\Client;
use \stdClass;

/**
 * @author Christian Putzke <christian.putzke@graviox.de>
 * @author Andreas Goetz <cpuidle@gmx.de>
 * @copyright Christian Putzke
 * @license http://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */

class Backend
{
    use ClientTrait;

    /**
     * CardDAV server url
     * @var string
     */
    private $url;

    /**
     * VCard File URL Extension
     * @var string
     */
    private $vcard_extension = '.vcf';

    /**
     * Progress callback
     * @var callable
     */
    private $callback;

    /**
     * Set substitutions of links to embedded data
     * @var array
     */
    private $substitutes = [];

    /**
     * Cached http client
     * @var Client|null
     */
    private $client;

    /**
     * Constructor
     * Sets the CardDAV server url
     *
     * @param   string  $url    CardDAV server url
     */
    public function __construct(string $url=null)
    {
        if ($url) {
            $this->setUrl($url);
        }

        $this->setClientOptions([
            'headers' => [
                'Depth' => 1
            ]
        ]);
    }

    /**
     * Set the properties/elements to be substituted
     *
     * @param   array $elements        the properties whose value should be replaced ('LOGO', 'KEY', 'PHOTO' or 'SOUND')
     */
    public function setSubstitutes($elements)
    {
        foreach ($elements as $element) {
            $this->substitutes[] = strtolower($element);
        }
    }

    /**
     * Set and normalize server url
     *
     * @param string $url
     * @return void
     */
    public function setUrl(string $url)
    {
        $this->url = rtrim($url, '/') . '/';

        // workaround for providers that don't use the default .vcf extension
        if (strpos($this->url, "google.com")) {
            $this->vcard_extension = '';
        }
    }

    /**
     * Set progress callback
     */
    public function setProgress(callable $callback=null)
    {
        $this->callback = $callback;
    }

    /**
     * Get initialized http client. Improves download performance by up to x7
     *
     * @return Client
     */
    private function getCachedClient(): Client
    {
        if (!$this->client) {
            $this->client = $this->getClient();
        }
        return $this->client;
    }

    /**
     * Gets all vCards including additional information from the CardDAV server
     *
     * @return  stdClass[]   All parsed Vcards from backend
     */
    public function getVcards(): array
    {
        $response = $this->getCachedClient()->request('PROPFIND', $this->url);
        $body = (string)$response->getBody();
        return $this->processPropFindResponse($body);
    }

    /**
     * If elements are declared as to be substituted,
     * the data from possibly linked sources are embedded directly into the vCard
     *
     * @param   stdClass $vcard             single parsed vCard
     * @param   string $substituteID        the property whose value is to be replaced ('logo', 'key', 'photo' or 'sound')
     * @return  stdClass                    single vCard with embedded value
     */
    private function embedBase64(stdClass $vcard, string $substituteID): stdClass
    {
        if (!property_exists($vcard, $substituteID)) {
            return $vcard;
        }

        if (!preg_match("/^http/", $vcard->{$substituteID})) {    // no external URL set -> must be already base64 or local
            return $vcard;
        }

        // check if mime is linked onto the same server
        $serv = explode('/', $this->url, 4);                   // get the beginning of the current server adress
        $link = explode('/', $vcard->{$substituteID}, 4);      // get the beginning of the linked adress
        if (strcasecmp($serv[2], $link[2]) !== 0) {            // if they aren´t equal authorisation will fail!
            return $vcard;
        }

        $embedded = $this->getLinkedData($vcard->{$substituteID});   // get the data from the external URL
        $types = '';
        switch ($vcard->version) {                             // the different vCard versions must be considered
            case '3.0':
                $types = "TYPE=" . strtoupper($embedded['subtype']) . ";ENCODING=b";
                break;
            case '4.0':
                $types = "data:" . $embedded['mimetype'] . ";base64";
                break;
        }

        $rawField  = "raw" . ucfirst($substituteID);
        $dataField = $substituteID . "Data";
        $vcard->$rawField  = $embedded['data'];
        $vcard->$dataField = $types;

        return $vcard;
    }

    /**
     * Delivers an array including the previously linked data and its mime type details
     * a mime type is composed of a type, a subtype, and optional parameters (e.g. "; charset=UTF-8")
     *
     * @param    string $uri           URL of the external linked data
     * @return   array ['mimetype',    e.g. "image/jpeg"
     *                  'type',        e.g. "audio"
     *                  'subtype',     e.g. "mpeg"
     *                  'parameters',  whatever
     *                  'data']        the base64 encoded data
     * @throws \Exception
     */
    public function getLinkedData(string $uri): array
    {
        $response = $this->getCachedClient()->request('GET', $uri);
        $contentType = $response->getHeader('Content-Type');

        @list($mimeType, $parameters) = explode(';', $contentType[0], 2);
        @list($type, $subType) = explode('/', $mimeType);

        $externalData = [
            'mimetype'   => $mimeType ?? '',
            'type'       => $type ?? '',
            'subtype'    => $subType ?? '',
            'parameters' => $parameters ?? '',
            'data'       => (string)$response->getBody(),
        ];
        return $externalData;
    }

    /**
     * Gets a clean vCard from the CardDAV server
     *
     * @param    string  $vcard_id   vCard id on the CardDAV server
     * @return   stdClass            vCard (text/vcard)
     */
    public function getVcard(string $vcard_id): stdClass
    {
        $vcard_id = str_replace($this->vcard_extension, '', $vcard_id) . $this->vcard_extension;
        $response = $this->getCachedClient()->request('GET', $this->url . $vcard_id);

        $body = (string)$response->getBody();

        $parser = new Parser($body);
        $vcard = $parser->getCardAtIndex(0);

        if (isset($this->substitutes)) {
            foreach ($this->substitutes as $substitute) {
                $vcard = $this->embedBase64($vcard, $substitute);
            }
        }
        if (is_callable($this->callback)) {
            ($this->callback)();
        }

        return $vcard;
    }

    /**
     * Process CardDAV XML response
     *
     * @param   string  $response           CardDAV XML response
     * @return  stdClass[]                  Parsed Vcards from CardDAV XML response
     */
    private function processPropFindResponse(string $response): array
    {
        $response = $this->cleanResponse($response);
        $xml = new \SimpleXMLElement($response);

        $cards = [];

        foreach ($xml->response as $response) {
            if ((preg_match('/vcard/', $response->propstat->prop->getcontenttype) || preg_match('/vcf/', $response->href)) &&
              !$response->propstat->prop->resourcetype->collection) {
                $id = basename($response->href);
                $id = str_replace($this->vcard_extension, '', $id);

                $cards[] = $this->getVcard($id);
            }
        }

        return $cards;
    }

    /**
     * Cleans CardDAV XML response
     *
     * @param   string  $response   CardDAV XML response
     * @return  string              Cleaned CardDAV XML response
     */
    private function cleanResponse($response)
    {
        $response = utf8_encode($response);
        $response = str_replace('D:', '', $response);
        $response = str_replace('d:', '', $response);
        $response = str_replace('C:', '', $response);
        $response = str_replace('c:', '', $response);

        return $response;
    }
}
