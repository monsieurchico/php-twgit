<?php

namespace NMR\Connector;

/**
 * Class GithubConnector
 */
class GithubConnector extends AbstractConnector
{
    /** @var string */
    protected $repository;

    /** @var string */
    protected $user;

    /** @var string */
    protected $accessToken;

    /**
     * GithubConnector constructor.
     *
     * @param string $repository
     * @param string $user
     * @param string $accessToken
     */
    public function __construct($repository, $user, $accessToken)
    {
        $this->repository = $repository;
        $this->user = $user;
        $this->accessToken = $accessToken;
    }

    /**
     * @param $issue
     *
     * @return string
     */
    public function getIssueTitle($issue)
    {
        $response = $this->getClient()->get(sprintf(
            'https://api.github.com/repos/%s/%s/issues/%s?access_token=%s',
            $this->user, $this->repository, $issue, $this->accessToken
        ), ['http_errors' => false]);

        if (200 === $response->getStatusCode()) {
            $data = json_decode($response->getBody()->getContents(), true);

            return isset($data['title']) ? $data['title'] : '';
        }

        return '';
    }
}