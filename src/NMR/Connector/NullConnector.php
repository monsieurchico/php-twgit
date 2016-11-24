<?php

namespace NMR\Connector;

/**
 * Class NullConnector
 */
class NullConnector extends AbstractConnector
{
    /**
     * @param string $project
     * @param string $version
     *
     * @return bool
     */
    public function createProjectVersion($version)
    {
        return true;
    }

    /**
     * @param string $issue
     *
     * @return null
     */
    public function getIssueTitle($issue)
    {
        return null;
    }

    /**
     * @param string $issue
     * @param string $version
     *
     * @return bool
     */
    public function setIssueFixVersion($issue, $version)
    {
        return true;
    }

    /**
     * @return string
     */
    function getName()
    {
        return 'null';
    }
}