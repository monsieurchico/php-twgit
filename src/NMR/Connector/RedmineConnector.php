<?php

namespace NMR\Connector;

/**
 * Class RedmineConnector
 */
class RedmineConnector extends AbstractConnector
{
    /** @var string */
    protected $domain;

    /** @var string */
    protected $apiKey;

    /**
     * RedmineConnector constructor.
     *
     * @param string $domain
     * @param string $apiKey
     */
    public function __construct($domain, $apiKey)
    {
        $this->domain = $domain;
        $this->apiKey = $apiKey;
    }

    /**
     * @param $issue
     *
     * @return string
     */
    public function getIssueTitle($issue)
    {
        return "my subject";
    }
}