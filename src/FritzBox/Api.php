<?php

namespace Andig\FritzBox;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Ringcentral\Psr7;

/**
 * Copyright (c) 2019 Andreas Götz
 * @license MIT
 */
class Api
{
    private $username;
    private $password;
    private $url;

    protected $sid = '0000000000000000';

    /**
     * Do not use this directly! Rather use {@see getClient()}
     *
     * @var Client
     */
    private $client;

    /**
     * Execute fb login
     *
     * @access public
     */
    public function __construct($url = 'https://fritz.box', $username = false, $password = false)
    {
        // set FRITZ!Box-IP and URL
        $this->url = rtrim($url, '/');
        $this->username = $username;
        $this->password = $password;

        $this->initSID();
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
        return $options;
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
    protected function initSID()
    {
        $url = $this->url . '/login_sid.lua';

        // read the current status
        $resp = $this->getClient()->request('GET', $url);
        if (200 !== $resp->getStatusCode()) {
            throw new \Exception('Received HTTP ' . $resp->getStatusCode());
        }

        // process response
        $xml = simplexml_load_string((string)$resp->getBody());
        if ($xml->SID != '0000000000000000') {
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
        if (200 !== $resp->getStatusCode()) {
            throw new \Exception('Received HTTP ' . $resp->getStatusCode());
        }

        // finger out the SID from the response
        $xml = simplexml_load_string((string)$resp->getBody());
        if ($xml->SID != '0000000000000000') {
            $this->sid = (string)$xml->SID;
            return;
        }

        throw new \Exception('ERROR: Login failed with an unknown response.');
    }
}
