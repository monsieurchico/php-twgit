<?php

namespace NMR\Connector;

/**
 * Class TrelloConnector
 */
class TrelloConnector extends AbstractConnector
{
    /** @var string */
    protected $domain;

    /** @var string */
    protected $applicationKey;

    /** @var string */
    protected $token;

    /**
     * TrelloConnector constructor.
     *
     * @param string $domain
     * @param string $applicationKey
     * @param string $token
     */
    public function __construct($domain, $applicationKey, $token)
    {
        $this->domain = $domain;
        $this->applicationKey = $applicationKey;
        $this->token = $token;
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