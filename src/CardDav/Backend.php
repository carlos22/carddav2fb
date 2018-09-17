<?php

namespace Andig\CardDav;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Ringcentral\Psr7;

/**
 * @author Christian Putzke <christian.putzke@graviox.de>
 * @copyright Christian Putzke
 * @link http://www.graviox.de/
 * @link https://twitter.com/cputzke/
 * @since 24.05.2015
 * @version 0.7
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
     * Constructor
     * Sets the CardDAV server url
     *
     * @param   string  $url    CardDAV server url
     */
    public function __construct(string $url=null) {
        if ($url) {
            $this->setUrl($url);
        }
    }

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

    private function getClient()
    {
        if (!$this->client) {
            $this->client = new Client($this->getClientOptions());
        }

        return $this->client;
    }

    private function getClientOptions($options = [])
    {
        if ($this->username) {
            $options['auth'] = [$this->username, $this->password, $this->authentication];
        }

        return $options;
    }

    public function fetchImage($uri)
    {
        $request = new Request('GET', $uri);

        $response = $this->getClient()->send($request);

        if (200 !== $response->getStatusCode()) {
            throw new \Exception('Received HTTP ' . $response->getStatusCode());
        }

        return (string)$response->getBody();
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

            if (is_callable($this->callback)) {
                ($this->callback)();
            }

            return $body;
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
