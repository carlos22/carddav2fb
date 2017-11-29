<?php

namespace Andig\FritzBox;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Ringcentral\Psr7;

/**
 * Extended from https://github.com/jens-maus/carddav2fb
 * Public Domain
 */
class Api
{
    private $username;
    private $password;
    private $url;

    protected $sid = '0000000000000000';

    /**
     * the constructor, initializes the object and calls the login method
     *
     * @access public
     */
    public function __construct($url = 'https://fritz.box', $user_name = false, $password = false, $force_local_login = false)
    {
        // init the config object
        // $this->config = new Config();

        // set FRITZ!Box-IP and URL
        $this->url = $url;
        $this->username = $user_name;
        $this->password = $password;

        $this->sid = $this->initSID();
    }

    /**
     * do a POST request on the box
     * the main cURL wrapper handles the command
     *
     * @param  array  $formfields    an associative array with the POST fields to pass
     * @return string                the raw HTML code returned by the Fritz!Box
     */
    public function doPostForm($formfields = array())
    {
        $ch = curl_init();

        if (isset($formfields['getpage']) && strpos($formfields['getpage'], '.lua') > 0) {
            curl_setopt($ch, CURLOPT_URL, $this->url . $formfields['getpage'] . '?sid=' . $this->sid);
            unset($formfields['getpage']);
        } else {
            // add the sid, if it is already set
            if ($this->sid != '0000000000000000') {
                $formfields['sid'] = $this->sid;
            }
            curl_setopt($ch, CURLOPT_URL, $this->url . '/cgi-bin/webcm');
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($formfields));
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

    public function doPostFile($formfields = array(), $filefileds = array())
    {
        $ch = curl_init();

        // add the sid, if it is already set
        if ($this->sid != '0000000000000000') {
            // 'sid' MUST be the first POST variable!!! (otherwise it will not work!!)
            // therfore we use array_merge to ensuere the foreach outputs 'sid' fist
            $formfields = array_merge(array('sid' => $this->sid), $formfields);
            //$formfields['sid'] = $this->sid;
        }
        curl_setopt($ch, CURLOPT_URL, $this->url . '/cgi-bin/firmwarecfg');
        curl_setopt($ch, CURLOPT_POST, 1);

        // enable for debugging:
        //curl_setopt($ch, CURLOPT_VERBOSE, TRUE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        // if filefileds not specified ('@/path/to/file.xml;type=text/xml' works fine)
        if (empty($filefileds)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $formfields); // http_build_query
        }
        // post calculated raw data
        else {
            $header = $this->_create_custom_file_post_header($formfields, $filefileds);
            curl_setopt(
                $ch,
                CURLOPT_HTTPHEADER,
                array(
                'Content-Type: multipart/form-data; boundary=' . $header['delimiter'], 'Content-Length: ' . strlen($header['data']) )
                );

            curl_setopt($ch, CURLOPT_POSTFIELDS, $header['data']);
        }

        $output = curl_exec($ch);

        // curl error
        if (curl_errno($ch)) {
            throw new \Exception(curl_error($ch)." (".curl_errno($ch).")");
        }

        // finger out an error message, if given
        preg_match('@<p class="ErrorMsg">(.*?)</p>@is', $output, $matches);
        if (isset($matches[1])) {
            throw new \Exception(str_replace('&nbsp;', ' ', $matches[1]));
        }

        curl_close($ch);
        return $output;
    }

    private function _create_custom_file_post_header($postFields, $fileFields)
    {
        // form field separator
        $delimiter = '-------------' . uniqid();

        /*
            // file upload fields: name => array(type=>'mime/type',content=>'raw data')
            $fileFields = array(
                'file1' => array(
                    'type' => 'text/xml',
                    'content' => '...your raw file content goes here...',
                    'filename' = 'filename.xml'
                ),
            );
            // all other fields (not file upload): name => value
            $postFields = array(
                'otherformfield'   => 'content of otherformfield is this text',
            );
         */

        $data = '';

        // populate normal fields first (simpler)
        foreach ($postFields as $name => $content) {
            $data .= "--" . $delimiter . "\r\n";
            $data .= 'Content-Disposition: form-data; name="' . urlencode($name) . '"';
            $data .= "\r\n\r\n";
            $data .= $content;
            $data .= "\r\n";
        }
        // populate file fields
        foreach ($fileFields as $name => $file) {
            $data .= "--" . $delimiter . "\r\n";
            // "filename" attribute is not essential; server-side scripts may use it
            $data .= 'Content-Disposition: form-data; name="' . urlencode($name) . '";' .
                     ' filename="' . $file['filename'] . '"' . "\r\n";

            //$data .= 'Content-Transfer-Encoding: binary'."\r\n";
            // this is, again, informative only; good practice to include though
            $data .= 'Content-Type: ' . $file['type'] . "\r\n";
            // this endline must be here to indicate end of headers
            $data .= "\r\n";
            // the file itself (note: there's no encoding of any kind)
            $data .= $file['content'] . "\r\n";
        }
        // last delimiter
        $data .= "--" . $delimiter . "--\r\n";

        return array('delimiter' => $delimiter, 'data' => $data);
    }

    /**
     * do a GET request on the box
     * the main cURL wrapper handles the command
     *
     * @param  array  $params    an associative array with the GET params to pass
     * @return string            the raw HTML code returned by the Fritz!Box
     */
    public function doGetRequest($params = array())
    {
        // add the sid, if it is already set
        if ($this->sid != '0000000000000000') {
            $params['sid'] = $this->sid;
        }

        if (strpos($params['getpage'], '.lua') > 0) {
            $getpage = $params['getpage'] . '?';
            unset($params['getpage']);
        } else {
            $getpage = '/cgi-bin/webcm?';
        }

        $url = $this->url . $getpage . http_build_query($params);

        $this->client = $this->client ?? new Client();
        $response = $this->client->send(new Request('GET', $url));

        if (200 !== $response->getStatusCode()) {
            throw new \Exception('Received HTTP ' . $response->getStatusCode());
        }

        return (string)$response->getBody();
    }

    /**
     * the login method, handles the secured login-process
     * newer firmwares (xx.04.74 and newer) need a challenge-response mechanism to prevent Cross-Site Request Forgery attacks
     * see http://www.avm.de/de/Extern/Technical_Note_Session_ID.pdf for details
     *
     * @return string                a valid SID, if the login was successful, otherwise throws an Exception with an error message
     */
    protected function initSID()
    {
        $loginpage = '/login_sid.lua';

        // read the current status
        $login = $this->doGetRequest(array('getpage' => $loginpage));

        $xml = simplexml_load_string($login);
        if ($xml->SID != '0000000000000000') {
            return $xml->SID;
        }

        // the challenge-response magic, pay attention to the mb_convert_encoding()
        $response = $xml->Challenge . '-' . md5(mb_convert_encoding($xml->Challenge . '-' . $this->password, "UCS-2LE", "UTF-8"));

        // do the login
        $formfields = array(
            'getpage' => $loginpage,
            'username' => $this->username,
            'response' => $response
        );

        $output = $this->doGetRequest($formfields);

        // finger out the SID from the response
        $xml = simplexml_load_string($output);
        if ($xml->SID != '0000000000000000') {
            return (string)$xml->SID;
        }

        throw new \Exception('ERROR: Login failed with an unknown response.');
    }

    /**
     * a getter for the session ID
     *
     * @return string                $this->sid
     */
    public function getSID()
    {
        return $this->sid;
    }
}
