<?php

namespace Andig\FritzBox;

use Andig\Http\ClientTrait;

define('EMPTY_SID', '0000000000000000');

/**
 * Copyright (c) 2019 Andreas Götz
 * @license MIT
 */
class Api
{
    use ClientTrait;

    /** @var  string */
    protected $username;

    /** @var  string */
    protected $password;

    /** @var  string */
    protected $url;

    /** @var  string */
    protected $sid = EMPTY_SID;

    /**
     * Execute fb login
     *
     * @access public
     */
    public function __construct($url = 'https://fritz.box')
    {
        $this->url = rtrim($url, '/');
    }

    /**
     * Get session ID
     *
     * @return string SID
     */
    public function getSID()
    {
        return $this->sid;
    }

    /**
     * Multi-part file uploads
     *
     * @param array $formFields
     * @param array $fileFields
     * @return string POST result
     * @throws Exception
     */
    public function postFile(array $formFields, array $fileFields)
    {
        $multipart = [];

        // sid must be first parameter
        $formFields = array_merge(array('sid' => $this->sid), $formFields);

        foreach ($formFields as $key => $val) {
            $multipart[] = [
                'name' => $key,
                'contents' => $val,
            ];
        }

        foreach ($fileFields as $name => $file) {
            $multipart[] = [
                'name' => $name,
                'filename' => $file['filename'],
                'contents' => $file['content'],
                'headers' => [
                    'Content-Type' => $file['type'],
                ],
            ];
        }

        $url = $this->url . '/cgi-bin/firmwarecfg';
        $resp = $this->getClient()->request('POST', $url, [
            'multipart' => $multipart,
        ]);

        if (200 !== $resp->getStatusCode()) {
            throw new \Exception('Received HTTP ' . $resp->getStatusCode());
        }

        return (string)$resp->getBody();
    }

    /**
     * Login, throws on failure
     *
     * @throws Exception
     */
    protected function login()
    {
        $url = $this->url . '/login_sid.lua';

        // read the current status
        $resp = $this->getClient()->request('GET', $url);
        $xml = simplexml_load_string((string)$resp->getBody());
        if ($xml->SID != EMPTY_SID) {
            $this->sid = (string)$xml->SID;
            return;
        }

        // the challenge-response magic, pay attention to the mb_convert_encoding()
        $response = $xml->Challenge . '-' . md5(mb_convert_encoding($xml->Challenge . '-' . $this->password, "UCS-2LE", "UTF-8"));

        // login
        $resp = $this->getClient()->request('GET', $url, [
            'query' => [
                'username' => $this->username,
                'response' => $response,
            ]
        ]);

        // retrieve SID from response
        $xml = simplexml_load_string((string)$resp->getBody());
        if ($xml->SID != EMPTY_SID) {
            $this->sid = (string)$xml->SID;
            return;
        }

        throw new \Exception('ERROR: Login failed with an unknown response.');
    }
}
