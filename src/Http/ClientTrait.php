<?php

namespace Andig\Http;

use GuzzleHttp\Client;

/**
 * @author Andreas Götz <cpuidle@gmx.de>
 * @copyright Andreas Götz
 * @license MIT
 */

trait ClientTrait
{
    /** @var string|null */
    protected $username;

    /** @var string|null */
    protected $password;

    /** @var array */
    protected $clientOptions = [];

    /** @var Client */
    private $client;

    /**
     * Set credentials
     */
    public function setAuth(string $username, string $password)
    {
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * Get initialized HTTP client
     *
     * @return Client
     */
    protected function getClient(): Client
    {
        if (!$this->client) {
            $this->client = new Client($this->getClientOptions());
        }

        return $this->client;
    }

    /**
     * Set default HTTP client options
     *
     * @param array $options
     */

    public function setClientOptions(array $options = [])
    {
        $this->clientOptions = $options;
    }

    /**
     * HTTP client options
     *
     * @param array $options
     * @return array
     */
    protected function getClientOptions($options = []): array
    {
        // merge default options
        $options = array_merge($this->clientOptions, $options);

        if ($this->username) {
            $method = $this->clientOptions['auth'] ?? 'basic';
            $options['auth'] = [$this->username, $this->password, $method];
        } else {
            unset($options['auth']);
        }

        return $options;
    }

}
