<?php namespace CardDavPHP;

/**
 * CardDAV PHP
 *
 * Simple CardDAV query
 * --------------------
 * $carddav = new CardDavBackend('https://davical.example.com/user/contacts/');
 * $carddav->setAuth('username', 'password');
 * echo $carddav->get();
 *
 *
 * Simple vCard query
 * ------------------
 * $carddav = new CardDavBackend('https://davical.example.com/user/contacts/');
 * $carddav->setAuth('username', 'password');
 * echo $carddav->getVcard('0126FFB4-2EB74D0A-302EA17F');
 *
 *
 * XML vCard query
 * ------------------
 * $carddav = new CardDavBackend('https://davical.example.com/user/contacts/');
 * $carddav->setAuth('username', 'password');
 * echo $carddav->getXmlVcard('0126FFB4-2EB74D0A-302EA17F');
 *
 *
 * Check CardDAV server connection
 * -------------------------------
 * $carddav = new CardDavBackend('https://davical.example.com/user/contacts/');
 * $carddav->setAuth('username', 'password');
 * var_dump($carddav->checkConnection());
 *
 *
 * CardDAV delete query
 * --------------------
 * $carddav = new CardDavBackend('https://davical.example.com/user/contacts/');
 * $carddav->setAuth('username', 'password');
 * $carddav->delete('0126FFB4-2EB74D0A-302EA17F');
 *
 *
 * CardDAV add query
 * --------------------
 * $vcard = 'BEGIN:VCARD
 * VERSION:3.0
 * UID:1f5ea45f-b28a-4b96-25as-ed4f10edf57b
 * FN:Christian Putzke
 * N:Christian;Putzke;;;
 * EMAIL;TYPE=OTHER:christian.putzke@graviox.de
 * END:VCARD';
 *
 * $carddav = new CardDavBackend('https://davical.example.com/user/contacts/');
 * $carddav->setAuth('username', 'password');
 * $vcard_id = $carddav->add($vcard);
 *
 *
 * CardDAV update query
 * --------------------
 * $vcard = 'BEGIN:VCARD
 * VERSION:3.0
 * UID:1f5ea45f-b28a-4b96-25as-ed4f10edf57b
 * FN:Christian Putzke
 * N:Christian;Putzke;;;
 * EMAIL;TYPE=OTHER:christian.putzke@graviox.de
 * END:VCARD';
 *
 * $carddav = new CardDavBackend('https://davical.example.com/user/contacts/');
 * $carddav->setAuth('username', 'password');
 * $carddav->update($vcard, '0126FFB4-2EB74D0A-302EA17F');
 *
 *
 * CardDAV debug
 * -------------
 * $carddav = new CardDavBackend('https://davical.example.com/user/contacts/');
 * $carddav->enableDebug();
 * $carddav->setAuth('username', 'password');
 * $carddav->get();
 * var_dump($carddav->getDebug());
 *
 *
 * CardDAV server list
 * -------------------
 * DAViCal:                  https://example.com/{resource|principal|username}/{collection}/
 * Apple Addressbook Server: https://example.com/addressbooks/users/{resource|principal|username}/{collection}/
 * memotoo:                  https://sync.memotoo.com/cardDAV/
 * SabreDAV:                 https://example.com/addressbooks/{resource|principal|username}/{collection}/
 * ownCloud:                 https://example.com/apps/contacts/carddav.php/addressbooks/{resource|principal|username}/{collection}/
 * SOGo:                     https://example.com/SOGo/dav/{resource|principal|username}/Contacts/{collection}/
 * Google (direct):		 			 https://google.com/m8/carddav/principals/__uids__/{username}/lists/default/
 *
 *
 * @author Christian Putzke <christian.putzke@graviox.de>
 * @copyright Christian Putzke
 * @link http://www.graviox.de/
 * @link https://twitter.com/cputzke/
 * @since 24.05.2015
 * @version 0.7
 * @license http://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 *
 */

class CardDavBackend
{
    /**
     * CardDAV PHP Version
     *
     * @constant    string
     */
    const VERSION = '0.7';

    /**
     * User agent displayed in http requests
     *
     * @constant    string
     */
    const USERAGENT = 'CardDAV PHP/';

    /**
     * CardDAV server url
     *
     * @var     string
     */
    private $url = null;

    /**
     * CardDAV server url_parts
     *
     * @var     array
     */
    private $url_parts = null;

    /**
     * VCard File URL Extension
     * 
     * @var string
     */
    private $url_vcard_extension = '.vcf';

    /**
     * Authentication string
     *
     * @var     string
     */
    private $auth = null;

    /**
    * Authentication: username
    *
    * @var  string
    */
    private $username = null;

    /**
    * Authentication: password
    *
    * @var  string
    */
    private $password = null;

    /**
     * Characters used for vCard id generation
     *
     * @var     array
     */
    private $vcard_id_chars = array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 'A', 'B', 'C', 'D', 'E', 'F');

    /**
     * CardDAV server connection (curl handle)
     *
     * @var     resource
     */
    private $curl;

    /**
     * Follow redirects (Location Header)
     * 
     * @var boolean
     */
    private $follow_redirects = true;
    
    /**
     * Maximum redirects to follow
     *
     * @var integer
     */
    private $follow_redirects_count = 3;

    /**
     * Debug on or off
     *
     * @var     boolean
     */
    private $debug = false;

    /**
     * All available debug information
     *
     * @var     array
     */
    private $debug_information = array();

    /**
     * Exception codes
     */
    const EXCEPTION_WRONG_HTTP_STATUS_CODE_GET              = 1000;
    const EXCEPTION_WRONG_HTTP_STATUS_CODE_GET_VCARD        = 1001;
    const EXCEPTION_WRONG_HTTP_STATUS_CODE_GET_XML_VCARD    = 1002;
    const EXCEPTION_WRONG_HTTP_STATUS_CODE_DELETE           = 1003;
    const EXCEPTION_WRONG_HTTP_STATUS_CODE_ADD              = 1004;
    const EXCEPTION_WRONG_HTTP_STATUS_CODE_UPDATE           = 1005;
    const EXCEPTION_MALFORMED_XML_RESPONSE                  = 1006;
    const EXCEPTION_COULD_NOT_GENERATE_NEW_VCARD_ID             = 1007;


    /**
     * Constructor
     * Sets the CardDAV server url
     *
     * @param   string  $url    CardDAV server url
     */
    public function __construct($url = null)
    {
        if ($url !== null) {
            $this->setUrl($url);
        }
    }

    /**
     * Sets debug information
     *
     * @param   array   $debug_information      Debug information
     * @return  void
     */
    public function setDebug(array $debug_information)
    {
        $this->debug_information[] = $debug_information;
    }

    /**
    * Sets the CardDAV server url
    *
    * @param    string  $url    CardDAV server url
    * @return   void
    */
    public function setUrl($url)
    {
        $this->url = $url;

        if (substr($this->url, -1, 1) !== '/') {
            $this->url = $this->url . '/';
        }

        $this->url_parts = parse_url($this->url);

        // workaround for providers that don't use the default .vcf extension
        if (strpos($this->url, "google.com"))
        {
          $this->setVcardExtension("");
        }
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
     * @param   boolean $raw                Get response raw or simplified
     * @return  string                      Raw or simplified XML response
     */
    public function get($include_vcards = true, $raw = false)
    {
        // for owncloud&co. Doesn't work with OpenXchange/Appsuite
        $result = $this->query($this->url, 'PROPFIND');

        // for OpenXchange/Appsuite
        $content = '<?xml version="1.0" encoding="UTF-8" ?><D:sync-collection xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:carddav"><D:sync-token></D:sync-token><D:prop><D:getcontenttype/><D:getetag/><D:allprop/><C:address-data><C:allprop/></C:address-data></D:prop><C:filter/></D:sync-collection>';
        $content_type = 'application/xml';
        //$result = $this->query($this->url, 'REPORT', $content, $content_type);

        // DEBUG: print the response of the carddav-server
        //print_r($result);

        switch ($result['http_code'])
        {
            case 200:
            case 207:
                if ($raw === true) {
                    return $result['response'];
                } else {
                    return $this->simplify($result['response'], $include_vcards);
                }
        }

        throw new \Exception(
            "Woops, something's gone wrong! The CardDAV server returned the http status code {$result['http_code']}.",
            self::EXCEPTION_WRONG_HTTP_STATUS_CODE_GET
        );

    }

    /**
    * Gets a clean vCard from the CardDAV server
    *
    * @param    string  $vcard_id   vCard id on the CardDAV server
    * @return   string              vCard (text/vcard)
    */
    public function getVcard($vcard_id)
    {
        $vcard_id   = str_replace($this->url_vcard_extension, null, $vcard_id);
        $result     = $this->query($this->url . $vcard_id . $this->url_vcard_extension, 'GET');

        switch ($result['http_code'])
        {
            case 200:
            case 207:
                return $result['response'];
        }

        throw new \Exception(
            "Woops, something's gone wrong! The CardDAV server returned the http status code {$result['http_code']}.",
            self::EXCEPTION_WRONG_HTTP_STATUS_CODE_GET_VCARD
        );
    }

    /**
     * Gets a vCard + XML from the CardDAV Server
     *
     * @param   string      $vcard_id   vCard id on the CardDAV Server
     * @return  string                  Raw or simplified vCard (text/xml)
     */
    public function getXmlVcard($vcard_id)
    {
        $vcard_id = str_replace($this->url_vcard_extension, null, $vcard_id);

        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->setIndent(4);
        $xml->startDocument('1.0', 'utf-8');
            $xml->startElement('C:addressbook-multiget');
                $xml->writeAttribute('xmlns:D', 'DAV:');
                $xml->writeAttribute('xmlns:C', 'urn:ietf:params:xml:ns:carddav');
                $xml->startElement('D:prop');
                    $xml->writeElement('D:getetag');
                    $xml->writeElement('D:getlastmodified');
                $xml->endElement();
                $xml->writeElement('D:href', $this->url_parts['path'] . $vcard_id . $this->url_vcard_extension);
            $xml->endElement();
        $xml->endDocument();

        $result = $this->query($this->url, 'REPORT', $xml->outputMemory(), 'text/xml');

        switch ($result['http_code'])
        {
            case 200:
            case 207:
                return $this->simplify($result['response'], true);

        }

        throw new \Exception(
            "Woops, something's gone wrong! The CardDAV server returned the http status code {$result['http_code']}.",
            self::EXCEPTION_WRONG_HTTP_STATUS_CODE_GET_XML_VCARD
        );
    }

    /**
     * Enables the debug mode
     *
     * @return  void
     */
    public function enableDebug()
    {
        $this->debug = true;
    }

    /**
    * Checks if the CardDAV server is reachable
    *
    * @return   boolean
    */
    public function checkConnection()
    {
        $result = $this->query($this->url, 'OPTIONS');

        if ($result['http_code'] === 200) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Cleans the vCard
     *
     * @param   string  $vcard  vCard
     * @return  string  $vcard  vCard
     */
    private function cleanVcard($vcard)
    {
        $vcard = str_replace("\t", null, $vcard);

        return $vcard;
    }

    /**
     * Deletes an entry from the CardDAV server
     *
     * @param   string  $vcard_id   vCard id on the CardDAV server
     * @return  boolean
     */
    public function delete($vcard_id)
    {
        $result = $this->query($this->url . $vcard_id . $this->url_vcard_extension, 'DELETE');

        switch ($result['http_code'])
        {
            case 204:
                return true;
        }

        throw new \Exception(
            "Woops, something's gone wrong! The CardDAV server returned the http status code {$result['http_code']}.",
            self::EXCEPTION_WRONG_HTTP_STATUS_CODE_DELETE
        );
    }

    /**
     * Adds an entry to the CardDAV server
     *
     * @param   string  $vcard      vCard
     * @param   string  $vcard_id   vCard id on the CardDAV server
     * @return  string          The new vCard id
     */
    public function add($vcard, $vcard_id = null)
    {
        if ($vcard_id === null) {
            $vcard_id   = $this->generateVcardId();
        }
        $vcard  = $this->cleanVcard($vcard);
        $result = $this->query($this->url . $vcard_id . $this->url_vcard_extension, 'PUT', $vcard, 'text/vcard');

        switch($result['http_code'])
        {
            case 201:
                return $vcard_id;
        }

        throw new \Exception(
            "Woops, something's gone wrong! The CardDAV server returned the http status code {$result['http_code']}.",
            self::EXCEPTION_WRONG_HTTP_STATUS_CODE_ADD
        );
    }

    /**
     * Updates an entry to the CardDAV server
     *
     * @param   string  $vcard      vCard
     * @param   string  $vcard_id   vCard id on the CardDAV server
     * @return  boolean
     */
    public function update($vcard, $vcard_id)
    {
        try {
            return $this->add($vcard, $vcard_id);
        } catch (Exception $e) {
            throw new \Exception($e->getMessage(), self::EXCEPTION_WRONG_HTTP_STATUS_CODE_UPDATE);
        }
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

        try {
            $xml = new \SimpleXMLElement($response);
        } catch (Exception $e) {
            throw new \Exception(
                "The XML response seems to be malformed and can't be simplified!",
                self::EXCEPTION_MALFORMED_XML_RESPONSE,
                $e
            );
        }

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

                    if (!empty($id)) {
                        $simplified_xml->startElement('element');
                            $simplified_xml->writeElement('id', $id);
                            $simplified_xml->writeElement('etag', str_replace('"', null, $response->propstat->prop->getetag));
                            $simplified_xml->writeElement('last_modified', $response->propstat->prop->getlastmodified);

                        if ($include_vcards === true) {
                            $simplified_xml->writeElement('vcard', $this->getVcard($id));
                        }
                        $simplified_xml->endElement();
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
     * Curl initialization
     *
     * @return void
     */
    public function curlInit()
    {
        if (empty($this->curl)) {
            $this->curl = curl_init();
            curl_setopt($this->curl, CURLOPT_HEADER, true);
            curl_setopt($this->curl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($this->curl, CURLOPT_USERAGENT, self::USERAGENT.self::VERSION);

            if ($this->auth !== null) {
                curl_setopt($this->curl, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
                curl_setopt($this->curl, CURLOPT_USERPWD, $this->auth);
            }

            /* allow to follow redirects if activated */
            curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, $this->follow_redirects);
            if ($this->follow_redirects)
            {
              curl_setopt($this->curl, CURLOPT_MAXREDIRS, $this->follow_redirects_count);
            }
        }
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
        $this->curlInit();

        curl_setopt($this->curl, CURLOPT_URL, $url);
        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, $method);

        if ($content !== null) {
            curl_setopt($this->curl, CURLOPT_POST, true);
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, $content);
        } else {
            curl_setopt($this->curl, CURLOPT_POST, false);
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, null);
        }

        if ($content_type !== null) {
            curl_setopt($this->curl, CURLOPT_HTTPHEADER, array('Content-type: '.$content_type, 'Depth: 1'));
        } else {
            curl_setopt($this->curl, CURLOPT_HTTPHEADER, array('Depth: 1'));
        }

        $complete_response  = curl_exec($this->curl);
        $header_size        = curl_getinfo($this->curl, CURLINFO_HEADER_SIZE);
        $http_code          = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
        $header                 = trim(substr($complete_response, 0, $header_size));
        $response           = substr($complete_response, $header_size);

        $return = array(
            'response'      => $response,
            'http_code'         => $http_code
        );

        if ($this->debug === true) {
            $debug = $return;
            $debug['url']           = $url;
            $debug['method']        = $method;
            $debug['content']       = $content;
            $debug['content_type']  = $content_type;
            $debug['header']        = $header;
            $this->setDebug($debug);
        }

        return $return;
    }

    /**
     * Returns a valid and unused vCard id
     *
     * @return  string  $vcard_id   Valid vCard id
     */
    private function generateVcardId()
    {
        $vcard_id = null;

        for ($number = 0; $number <= 25; $number ++) {
            if ($number == 8 || $number == 17) {
                $vcard_id .= '-';
            } else {
                $vcard_id .= $this->vcard_id_chars[mt_rand(0, (count($this->vcard_id_chars) - 1))];
            }
        }

        try {
            $carddav = new CardDavBackend($this->url);
            $carddav->setAuth($this->username, $this->password);

            $result = $carddav->query($this->url . $vcard_id . $this->url_vcard_extension, 'GET');

            if ($result['http_code'] !== 404) {
                $vcard_id = $this->generateVcardId();
            }

            return $vcard_id;
        } catch (Exception $e) {
            throw new \Exception($e->getMessage(), self::EXCEPTION_COULD_NOT_GENERATE_NEW_VCARD_ID);
        }
    }

    /**
     * Destructor
     * Close curl connection if it's open
     *
     * @return  void
     */
    public function __destruct()
    {
        if (!empty($this->curl)) {
            curl_close($this->curl);
        }
    }
}

?>
