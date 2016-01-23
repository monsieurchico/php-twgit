<?php

namespace NMR\Connector;
use GuzzleHttp\Psr7\Request;

/**
 * Class JiraConnector
 */
class JiraConnector extends AbstractConnector
{
    /** @var string */
    protected $domain;

    /** @var string */
    protected $credentials;

    /**
     * JiraConnector constructor.
     *
     * @param string $domain
     * @param string $credentials
     */
    public function __construct($domain, $credentials)
    {
        $this->domain = $domain;
        $this->credentials = $credentials;
    }

    /**
     * @param string $project
     * @param string $version
     *
     * @return bool
     */
    public function createProjectVersion($project, $version)
    {
        $response = $this->getClient()->request(
            'post',
            sprintf(
                'https://%s/rest/api/latest/project/%s/version',
                $this->domain, $project
            ),
            array_merge(
                $this->getDefaultClientOptions(),
                [
                    'headers' => [
                        'application/json'
                    ],
                    'form_params' => [
                        'name' => $version,
                        'project' => $project,
                        'released' => false
                    ]
                ]
            )
        );

        return 200 === $response->getStatusCode();
    }

    /**
     * @param $issue
     *
     * @return string
     */
    public function getIssueTitle($issue)
    {
        $response = $this->getClient()->get(
            $this->getDefaultIssueUrl($issue),
            $this->getDefaultClientOptions()
        );

        if (200 === $response->getStatusCode()) {
            $parameters = json_decode($response->getBody(), true);

            return $parameters['fields']['summary'];
        }

        return '';
    }

    /**
     * @param string $issue
     * @param string $version
     */
    public function setIssueFixVersion($issue, $version)
    {
        $response = $this->getClient()->request(
            'post',
            $this->getDefaultIssueUrl($issue) . '/editmeta',
            array_merge(
                $this->getDefaultClientOptions(),
                [
                    'headers' => [
                        'application/json'
                    ],
                    'form_params' => [
                        'update' => [
                            'fixVersion' => [
                                'set' => $version
                            ]
                        ]
                    ]
                ]
            )
        );

        return 200 === $response->getStatusCode();
    }

    /**
     * @param $issue
     *
     * @return string
     */
    protected function getDefaultIssueUrl($issue)
    {
        return sprintf(
            'https://%s/rest/api/latest/issue/%s',
            $this->domain, $issue
        );
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
        ];
    }
}