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
     * CardDAV server url_parts
     *
     * @var     array
     */
    private $url_parts;

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
     * Characters used for vCard id generation
     *
     * @var     array
     */
    private $vcard_id_chars = array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 'A', 'B', 'C', 'D', 'E', 'F');

    /**
     * Progress callback
     */
    private $callback;

    /**
     * Constructor
     * Sets the CardDAV server url
     *
     * @param   string  $url    CardDAV server url
     */
    public function __construct($url)
    {
        $this->url = $url;

        if (substr($this->url, -1, 1) !== '/') {
            $this->url = $this->url . '/';
        }

        $this->url_parts = parse_url($this->url);

        // workaround for providers that don't use the default .vcf extension
        if (strpos($this->url, "google.com")) {
            $this->setVcardExtension("");
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
     * Sets the CardDAV vcard url extension
     *
     * Most providers do requests handling Vcards with .vcf, however
     * this isn't always the case and some providers (such as Google)
     * returned a 404 if the .vcf extension is used - or the other
     * way around, returning 404 unless .vcf is used.
     *
     * Both approaches are technically correct, see rfc635
     * http://tools.ietf.org/html/rfc6352
     *
     *
     * @param string  $extension  File extension
     * @return  void
     */
    public function setVcardExtension($extension)
    {
        $this->url_vcard_extension = $extension;
    }

    /**
     * Sets authentication information
     *
     * @param   string  $username   CardDAV server username
     * @param   string  $password   CardDAV server password
     * @return  void
     */
    public function setAuth($username, $password)
    {
        $this->username     = $username;
        $this->password     = $password;
        $this->auth         = $username . ':' . $password;
    }

    /**
     * Sets wether to follow redirects and if yes how often
     *
     * @param boolean $follow_redirects
     * @param integer $follow_redirects_count
     * @return  void
     */
    public function setFollowRedirects($follow_redirects, $follow_redirects_count = 3)
    {
        $this->follow_redirects = $follow_redirects && $follow_redirects_count > 0;
        $this->follow_redirects_count = $follow_redirects_count > 0 ? $follow_redirects_count : 0;
    }

    /**
     * Gets all available debug information
     *
     * @return  array   $this->debug_information    All available debug information
     */
    public function getDebug()
    {
        return $this->debug_information;
    }

    /**
     * Gets all vCards including additional information from the CardDAV server
     *
     * @param   boolean $include_vcards     Include vCards within the response (simplified only)
     * @return  string                      Raw or simplified XML response
     */
    public function get($include_vcards = true)
    {
        $response = $this->query($this->url, 'PROPFIND');

        if (in_array($response->getStatusCode(), [200,207])) {
            $body = (string)$response->getBody();
            return $this->simplify($body, $include_vcards);
        }

        throw new \Exception('Received HTTP ' . $response->getStatusCode());
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
     * @param   boolean $include_vcards     Include vCards or not
     * @return  string                      Simplified CardDAV XML response
     */
    private function simplify($response, $include_vcards = true)
    {
        $response = $this->cleanResponse($response);
        $xml = new \SimpleXMLElement($response);

        $simplified_xml = new \XMLWriter();
        $simplified_xml->openMemory();
        $simplified_xml->setIndent(4);

        $simplified_xml->startDocument('1.0', 'utf-8');
        $simplified_xml->startElement('response');

        if (!empty($xml->response)) {
            foreach ($xml->response as $response) {
                if ((preg_match('/vcard/', $response->propstat->prop->getcontenttype) || preg_match('/vcf/', $response->href)) &&
                  !$response->propstat->prop->resourcetype->collection) {
                    $id = basename($response->href);
                    $id = str_replace($this->url_vcard_extension, null, $id);

                    try {
                        $vcardData = $this->getVcard($id);

                        if (!empty($id)) {
                            $simplified_xml->startElement('element');
                            $simplified_xml->writeElement('id', $id);
                            $simplified_xml->writeElement('etag', str_replace('"', null, $response->propstat->prop->getetag));
                            $simplified_xml->writeElement('last_modified', $response->propstat->prop->getlastmodified);

                            if ($include_vcards === true) {
                                $simplified_xml->writeElement('vcard', $vcardData);
                            }
                            $simplified_xml->endElement();
                        }
                    } catch (\Exception $e) {
                        error_log("Error fetching vCard: {$id}: {$e->getMessage()}\n");
                    }
                } elseif (preg_match('/unix-directory/', $response->propstat->prop->getcontenttype)) {
                    if (isset($response->propstat->prop->href)) {
                        $href = $response->propstat->prop->href;
                    } elseif (isset($response->href)) {
                        $href = $response->href;
                    } else {
                        $href = null;
                    }

                    $url = str_replace($this->url_parts['path'], null, $this->url) . $href;
                    $simplified_xml->startElement('addressbook_element');
                    $simplified_xml->writeElement('display_name', $response->propstat->prop->displayname);
                    $simplified_xml->writeElement('url', $url);
                    $simplified_xml->writeElement('last_modified', $response->propstat->prop->getlastmodified);
                    $simplified_xml->endElement();
                }
            }
        }

        $simplified_xml->endElement();
        $simplified_xml->endDocument();

        return $simplified_xml->outputMemory();
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
        /*
                if ($content !== null) {
        error_log('POST');
                    curl_setopt($this->curl, CURLOPT_POST, true);
                    curl_setopt($this->curl, CURLOPT_POSTFIELDS, $content);
                } else {
                    curl_setopt($this->curl, CURLOPT_POST, false);
                    curl_setopt($this->curl, CURLOPT_POSTFIELDS, null);
                }
        */

        if (!isset($this->client)) {
            $this->client = new Client();
        }

        $request = new Request($method, $url, [
            'Depth' => '1'
        ]);

        if ($content_type) {
            $request = $request->withHeader('Content-type', $content_type);
        }

        if ($content) {
            $request = $request->withBody($content);
        }

        if ($this->username) {
            $credentials = base64_encode($this->username.':'.$this->password);
            $request = $request->withHeader('Authorization', 'Basic '.$credentials);
        }

        // error_log(Psr7\str($request));
        $response = $this->client->send($request);
        // error_log("\nHEADERS");
        // error_log(Psr7\str($response));
        // error_log("<<< HEADERS");

        return $response;
    }
}
