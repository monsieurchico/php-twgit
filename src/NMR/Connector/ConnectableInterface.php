<?php

namespace NMR\Connector;

/**
 * Interface ConnectableInterface
 *
 * @package NMR\Connector
 */
interface ConnectableInterface
{
    /**
     * @param string $project
     * @param string $version
     *
     * @return bool
     */
    function createProjectVersion($project, $version);

    /**
     * @param string $issue
     *
     * @return bool
     */
    function getIssueTitle($issue);

    /**
     * @param string $issue
     * @param string $version
     *
     * @return string
     */
    function setIssueFixVersion($issue, $version);
}