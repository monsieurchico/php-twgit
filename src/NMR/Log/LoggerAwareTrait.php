<?php

namespace NMR\Log;

use NMR\Log\Logger;

/**
 * Class LoggerAwareTrait
 */
trait LoggerAwareTrait
{
    /** @var Logger */
    protected $logger;

    /**
     * @return Logger
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @param Logger $logger
     *
     * @return LoggerAwareTrait
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;

        return $this;
    }
}