<?php

namespace Andig\CardDav;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Ringcentral\Psr7;
use Andig\Vcard\Parser;

/**
 * @author Christian Putzke <christian.putzke@graviox.de>
 * @copyright Christian Putzke
 * @license http://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */

class Backend
{
    /**
     * CardDAV server url
     *
     * @var     string
     */
    private $url;

    /**
     * VCard File URL Extension
     *
     * @var string
     */
    private $url_vcard_extension = '.vcf';

    /**
     * Authentication: username
     *
     * @var  string
     */
    private $username;

    /**
     * Authentication: password
     *
     * @var  string
     */
    private $password;

    /**
     * Authentication: method
     *
     * @var  string|null
     */
    private $authentication;

    /**
     * Progress callback
     */
    private $callback;

    /**
     * Do not use this directly! Rather use {@see getClient()}
     *
     * @var Client
     */
    private $client;

    /**
     * Set substitutions of links to embedded data
     */
    private $substitutes = [];

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
        $this->url = $url;

        if (substr($this->url, -1, 1) !== '/') {
            $this->url = $this->url . '/';
        }

        // workaround for providers that don't use the default .vcf extension
        if (strpos($this->url, "google.com")) {
            $this->url_vcard_extension = '';
        }
    }

    /**
     * Set progress callback
     */
    public function setProgress($callback = null)
    {
        $this->callback = $callback;
    }

    /**
     * Set credentials
     */
    public function setAuth(string $username, string $password, string $method = null)
    {
        $this->username = $username;
        $this->password = $password;
        $this->authentication = $method;
    }

    /**
     * Gets all vCards including additional information from the CardDAV server
     *
     * @param   boolean $include_vcards     Include vCards within the response (simplified only)
     * @return  string                      Raw or simplified XML response
     */
    public function getVcards($include_vcards = true)
    {
        $response = $this->query($this->url, 'PROPFIND');

        if (in_array($response->getStatusCode(), [200,207])) {
            $body = (string)$response->getBody();
            return $this->simplify($body, $include_vcards);
        }

        throw new \Exception('Received HTTP ' . $response->getStatusCode());
    }

    /**
     * Get initialized HTTP client
     *
     * @return Client
     */
    private function getClient(): Client
    {
        if (!$this->client) {
            $this->client = new Client($this->getClientOptions());
        }

        return $this->client;
    }

    /**
     * HTTP client options
     *
     * @param array $options
     * @return array
     */
    private function getClientOptions($options = []): array
    {
        if ($this->username) {
            $options['auth'] = [$this->username, $this->password, $this->authentication];
        }

        return $options;
    }

    /**
     * If elements are declared as to be substituted,
     * the data from possibly linked sources are embedded directly into the vCard
     *
     * @param   string $vcard               single parsed vCard
     * @param   string $substituteID        the property whose value is to be replaced ('logo', 'key', 'photo' or 'sound')
     * @param   string $server              the current CardDAV server adress
     * @return  string                      single vCard with embedded value
     */
    private function embedBase64($vcard, $substituteID, $server)
    {
        if (!array_key_exists($substituteID, $vcard)) {
            return $vcard;
        }
        if (!preg_match("/http/", $vcard->{$substituteID})) {    // no external URL set -> must be already base64 or local
            return $vcard;
        }
        // check if mime is linked onto the same server
        $serv = explode('/', $server, 4);                      // get the beginning of the current server adress
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
     * @throws Exception
     */
    public function getLinkedData($uri)
    {
        $request = new Request('GET', $uri);

        if ($this->username) {
            $credentials = base64_encode($this->username . ':' . $this->password);
            $request = $request->withHeader('Authorization', 'Basic ' . $credentials);
        }
        $response = $this->getClient()->send($request);

        if (200 !== $response->getStatusCode()) {
            throw new \Exception('Received HTTP ' . $response->getStatusCode());
        } else {
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
    }

    /**
     * Gets a clean vCard from the CardDAV server
     *
     * @param    string  $vcard_id   vCard id on the CardDAV server
     * @return   string              vCard (text/vcard)
     */
    public function getVcard($vcard_id)
    {
        $vcard_id = str_replace($this->url_vcard_extension, null, $vcard_id);
        $response = $this->query($this->url . $vcard_id . $this->url_vcard_extension, 'GET');

        if (in_array($response->getStatusCode(), [200,207])) {
            $body = (string)$response->getBody();

            $parser = new Parser($body);
            $vcard = $parser->getCardAtIndex(0);

            if (isset($this->substitutes)) {
                foreach ($this->substitutes as $substitute) {
                    $vcard = $this->embedBase64($vcard, $substitute, $this->url);
                }
            }
            if (is_callable($this->callback)) {
                ($this->callback)();
            }

            return $vcard;
        }

        throw new \Exception('Received HTTP ' . $response->getStatusCode());
    }

    /**
     * Simplify CardDAV XML response
     *
     * @param   string  $response           CardDAV XML response
     * @return  string                      Simplified CardDAV XML response
     */
    private function simplify(string $response): array
    {
        $response = $this->cleanResponse($response);
        $xml = new \SimpleXMLElement($response);

        $cards = [];

        foreach ($xml->response as $response) {
            if ((preg_match('/vcard/', $response->propstat->prop->getcontenttype) || preg_match('/vcf/', $response->href)) &&
              !$response->propstat->prop->resourcetype->collection) {
                $id = basename($response->href);
                $id = str_replace($this->url_vcard_extension, null, $id);

                $cards[] = $this->getVcard($id);
            }
        }

        return $cards;
    }

    /**
     * Cleans CardDAV XML response
     *
     * @param   string  $response   CardDAV XML response
     * @return  string  $response   Cleaned CardDAV XML response
     */
    private function cleanResponse($response)
    {
        $response = utf8_encode($response);
        $response = str_replace('D:', null, $response);
        $response = str_replace('d:', null, $response);
        $response = str_replace('C:', null, $response);
        $response = str_replace('c:', null, $response);

        return $response;
    }

    /**
     * Query the CardDAV server via curl and returns the response
     *
     * @param   string  $url                CardDAV server URL
     * @param   string  $method             HTTP method like (OPTIONS, GET, HEAD, POST, PUT, DELETE, TRACE, COPY, MOVE)
     * @param   string  $content            Content for CardDAV queries
     * @param   string  $content_type       Set content type
     * @return  array                       Raw CardDAV Response and http status code
     */
    private function query($url, $method, $content = null, $content_type = null)
    {
        $request = new Request($method, $url, [
            'Depth' => '1'
        ]);

        if ($content_type) {
            $request = $request->withHeader('Content-type', $content_type);
        }

        if ($content) {
            $request = $request->withBody($content);
        }

        $response = $this->getClient()->send($request);
        return $response;
    }
}
