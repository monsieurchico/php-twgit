<?php

namespace NMR\Connector;

use GuzzleHttp\Psr7\Request;

/**
 * Class GithubConnector
 */
class GithubConnector extends AbstractConnector
{
    const
        URL_TYPE_ISSUES = 'issues',
        URL_TYPE_MILESTONES = 'milestones'
    ;

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
     * @return string
     */
    function getName()
    {
        return 'github';
    }

    /**
     * @param string $version
     *
     * @return array|null
     */
    public function getProjectVersionInfo($version)
    {
        $response = $this->getClient()->get(
            $this->getUrl(self::URL_TYPE_MILESTONES),
            $this->getDefaultClientOptions()
        );

        if (self::HTTP_STATUS_OK === $response->getStatusCode()) {
            $data = json_decode($response->getBody()->getContents(), true);
            if (!empty($data)) {
                return current($data);
            }
        }

        return null;
    }

    /**
     * @param string $project
     * @param string $version
     *
     * @return bool
     */
    public function createProjectVersion($version)
    {
        if (empty($version)) {
            return true;
        }

        $response = $this->getClient()->post(
            $this->getUrl(self::URL_TYPE_MILESTONES),
            array_merge(
                $this->getDefaultClientOptions(),
                [
                    'headers' => [
                        'Accept' => 'application/json'
                    ],
                    'json' => [
                        'title' => $version,
                        'state' => 'open',
                        'description' => '[twgit] Milestone ' . $version
                    ]
                ]
            )
        );

        if (self::HTTP_STATUS_OK !== $response->getStatusCode()) {
            return null !== $this->getProjectVersionInfo($version);
        }

        return false;
    }

    /**
     * @param $issue
     *
     * @return string
     */
    public function getIssueTitle($issue)
    {
        $response = $this->getClient()->get(
            $this->getUrl(self::URL_TYPE_ISSUES, $issue),
            $this->getDefaultClientOptions()
        );

        if (self::HTTP_STATUS_OK === $response->getStatusCode()) {
            $data = json_decode($response->getBody()->getContents(), true);

            return isset($data['title']) ? $data['title'] : '';
        }

        return '';
    }

    /**
     * @param string $issue
     * @param string $version
     *
     * @return bool
     */
    public function setIssueFixVersion($issue, $version)
    {
        // retrieve milestone info
        $data = $this->getProjectVersionInfo($version);

        if (!empty($data)) {
            $milestone = (int)$data['number'];

            $response = $this->getClient()->post(
                $this->getUrl(self::URL_TYPE_ISSUES, $issue),
                array_merge(
                    $this->getDefaultClientOptions(),
                    [
                        'headers' => [
                            'Accept' => 'application/json'
                        ],
                        'json' => [
                            'milestone' => $milestone
                        ]
                    ]
                )
            );

            return self::HTTP_STATUS_OK === $response->getStatusCode();
        }

        return false;
    }

    /**
     * @param string $type
     * @param int    $number
     *
     * @return string
     */
    protected function getUrl($type, $number = null)
    {
        $url = sprintf(
            'https://api.github.com/repos/%s/%s/%s',
            $this->user, $this->repository, $type
        );

        if ($number) {
            return sprintf('%s/%s', $url, $number);
        }

        return $url;
    }

    /**
     * @return array
     */
    protected function getDefaultClientOptions()
    {
        return [
            'http_errors' => false,
            'query' => ['access_token' => $this->accessToken]
        ];
    }
}