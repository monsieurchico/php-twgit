<?php

namespace NMR\Connector;

use GuzzleHttp\Client;
use NMR\Client\GuzzleHttpClientAwareTrait;

/**
 * Class AbstractConnector
 */
abstract class AbstractConnector implements ConnectableInterface
{
    use GuzzleHttpClientAwareTrait;
}