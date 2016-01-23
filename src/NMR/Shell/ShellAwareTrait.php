<?php

namespace NMR\Shell;

/**
 * Class ShellAwareTrait
 */
trait ShellAwareTrait
{
    /** @var Shell */
    protected $shell;

    /**
     * @return Shell
     */
    public function getShell()
    {
        return $this->shell;
    }

    /**
     * @param Shell $shell
     *
     * @return ShellAwareTrait
     */
    public function setShell($shell)
    {
        $this->shell = $shell;

        return $this;
    }

    /**
     * @param array      $commandChunks
     * @param bool|false $silent
     * @param null       $errorMessage
     *
     * @return Response
     */
    protected function execShellCommand(array $commandChunks, $silent = false, $errorMessage = null)
    {
        return $this->getShell()->execCommand(
            $this->getShell()->buildCommand($commandChunks),
            $errorMessage,
            $silent
        );
    }
}