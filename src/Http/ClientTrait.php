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
    /** @var string */
    protected $username;

    /** @var string */
    protected $password;

    /** @var array */
    protected $clientOptions = [];

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
        return new Client($this->getClientOptions());
    }

    /**
     * Set default HTTP client options
     *
     * @param array $options
     */
    protected function setClientOptions(array $options = [])
    {
        $this->clientOptions = $options;
    }

    /**
     * Merge additional HTTP client options
     *
     * @param array $options
     */
    public function mergeClientOptions(array $options = [])
    {
        $this->clientOptions = array_merge($this->clientOptions, $options);
    }

    /**
     * HTTP client options
     *
     * @param array $options
     * @return array
     */
    protected function getClientOptions(array $options = []): array
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
