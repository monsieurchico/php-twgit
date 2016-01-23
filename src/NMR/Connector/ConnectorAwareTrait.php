<?php

namespace NMR\Connector;

/**
 * Class ConnectorAwareTrait
 */
trait ConnectorAwareTrait
{
    /** @var ConnectableInterface */
    private $connector;

    /**
     * @return ConnectableInterface
     */
    public function getConnector()
    {
        return $this->connector;
    }

    /**
     * @param ConnectableInterface $connector
     *
     * @return ConnectorAwareTrait
     */
    public function setConnector($connector)
    {
        $this->connector = $connector;

        return $this;
    }
}