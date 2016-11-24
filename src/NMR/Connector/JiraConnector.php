<?php

namespace NMR\Connector;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;

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
     * @return string
     */
    function getName()
    {
        return 'jira';
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

        if ($this->isResponseOK($response)) {
            return null !== $this->getProjectVersion($version);
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

        if ($this->isResponseOK($response)) {
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

        if ($this->isResponseOK($response)) {
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

        return $this->isResponseOK($response);
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

    /**
     * @param ResponseInterface $response
     * @return bool
     */
    protected function isResponseOK(ResponseInterface $response)
    {
        return 1 === intval($response->getStatusCode() / self::HTTP_STATUS_OK);
    }
}