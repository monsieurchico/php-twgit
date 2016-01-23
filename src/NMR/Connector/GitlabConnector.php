<?php

namespace NMR\Connector;

/**
 * Class GitlabConnector
 */
class GitlabConnector extends AbstractConnector
{
    /** @var string */
    protected $domain;

    /** @var string */
    protected $user;

    /**
     * GitlabConnector constructor.
     *
     * @param string $domain
     * @param string $user
     */
    public function __construct($domain, $user)
    {
        $this->domain = $domain;
        $this->user = $user;
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