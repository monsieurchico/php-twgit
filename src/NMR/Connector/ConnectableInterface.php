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
     * @return string
     */
    function getName();

    /**
     * @param string $version
     *
     * @return mixed
     */
    function createProjectVersion($version);

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