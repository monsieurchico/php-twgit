<?php

namespace NMR\Connector;
use GuzzleHttp\Psr7\Request;

/**
 * Class JiraConnector
 */
class JiraConnector extends AbstractConnector
{
    const
        URL_TYPE_ISSUES = 'issue',
        URL_TYPE_PROJECTS = 'project',
        URL_TYPE_VERSIONS = 'version'
    ;

    /** @var string */
    protected $domain;

    /** @var string */
    protected $project;

    /** @var string */
    protected $credentials;

    /**
     * JiraConnector constructor.
     *
     * @param string $domain
     * @param string $project
     * @param string $credentials
     */
    public function __construct($domain, $project, $credentials)
    {
        $this->domain = $domain;
        $this->project = $project;
        $this->credentials = $credentials;
    }


    /**
     * @param string $version
     *
     * @return bool
     */
    public function createProjectVersion($version)
    {
        if (empty($version) || empty($this->project)) {
            return true;
        }

        if ($this->getProjectVersion($version)) {
            return true;
        }

        $response = $this->getClient()->post(
            $this->getUrl(self::URL_TYPE_VERSIONS),
            array_merge(
                $this->getDefaultClientOptions(),
                [
                    'json' => [
                        'name' => $version,
                        'project' => $this->project,
                        'released' => false
                    ]
                ]
            )
        );

        return self::HTTP_STATUS_OK === $response->getStatusCode();
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
            $parameters = json_decode($response->getBody(), true);

            return $parameters['fields']['summary'];
        }

        return '';
    }

    /**
     * @return array|null
     */
    protected function getProjectVersions()
    {
        // check if version is available
        $response = $this->getClient()->get(
            sprintf('%s/versions', $this->getUrl(self::URL_TYPE_PROJECTS, $this->project)),
            $this->getDefaultClientOptions()
        );

        if (self::HTTP_STATUS_OK === $response->getStatusCode()) {
            return json_decode($response->getBody()->getContents(), true);
        }

        return null;
    }

    /**
     * @param string $version
     *
     * @return array|null
     */
    protected function getProjectVersion($version)
    {
        $foundVersionInfo = null;
        $versions = $this->getProjectVersions();

        if (!empty($versions)) {
            foreach ($versions as $versionInfo) {
                if ($versionInfo['name'] === $version) {
                    $foundVersionInfo = $versionInfo;
                    break;
                }
            }
        }

        return $foundVersionInfo;
    }

    /**
     * @param string $issue
     * @param string $version
     */
    public function setIssueFixVersion($issue, $version)
    {
        if (!$this->getProjectVersion($version)) {
            $this->createProjectVersion($version);
        }

        $response = $this->getClient()->put(
            $this->getUrl(self::URL_TYPE_ISSUES, $issue),
            array_merge(
                $this->getDefaultClientOptions(),
                [
                    'json' => [
                        'update' => [
                            'fixVersions' => [
                                [
                                    'set' => [
                                        [
                                            'name' => $version
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            )
        );

        return
            self::HTTP_STATUS_NO_CONTENT === $response->getStatusCode() ||
            self::HTTP_STATUS_OK === $response->getStatusCode();
    }

    /**
     * @param string $type
     * @param string $number
     *
     * @return string
     */
    protected function getUrl($type, $number = null)
    {
        $url = sprintf(
            'https://%s/rest/api/latest/%s',
            $this->domain, $type
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
        $credentials = base64_decode($this->credentials);
        list($user, $password) = explode(':', $credentials);

        return [
            'auth' => [$user, $password, 'basic'],
            'http_errors' => false,
            'headers' => [
                'application/json'
            ],
        ];
    }
}