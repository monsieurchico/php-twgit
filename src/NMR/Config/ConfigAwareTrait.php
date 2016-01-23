<?php

namespace NMR\Config;

/**
 * Class ConfigAwareTrait
 */
trait ConfigAwareTrait
{
    /** @var Config */
    protected $config;

    /**
     * @return Config
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @param Config $config
     *
     * @return ConfigAwareTrait
     */
    public function setConfig($config)
    {
        $this->config = $config;

        return $this;
    }
}