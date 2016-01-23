<?php

namespace NMR\Shell\Git;

use NMR\Shell\Response;

/**
 * Class GitAwareTrait
 */
trait GitAwareTrait
{
    /** @var Git */
    protected $git;

    /**
     * @return Git
     */
    public function getGit()
    {
        return $this->git;
    }

    /**
     * @param $git
     *
     * @return $this
     */
    public function setGit($git)
    {
        $this->git = $git;

        return $this;
    }

    /**
     * @param array      $commandChunks
     * @param bool|false $silent
     * @param null       $errorMessage
     * @param bool       $abortOnError
     *
     * @return Response
     */
    protected function execGitCommand(array $commandChunks, $silent = false, $errorMessage = null, $abortOnError = true)
    {
        return $this->getGit()->execCommand(
            $this->getGit()->buildCommand($commandChunks),
            $errorMessage,
            $silent,
            $abortOnError
        );
    }
}