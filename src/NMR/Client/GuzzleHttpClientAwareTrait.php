<?php

namespace NMR\Client;

use GuzzleHttp\Client;

/**
 * Class GuzzleHttpClientAwareTrait
 */
trait GuzzleHttpClientAwareTrait
{
    /** @var Client */
    protected $client;

    /**
     * @return Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @param Client $client
     *
     * @return GuzzleHttpClientAwareTrait
     */
    public function setClient(Client $client)
    {
        $this->client = $client;

        return $this;
    }
}