<?php

namespace NMR\Exception;

use Exception;

/**
 * Class WorkflowException
 */
class WorkflowException extends Exception
{
    /** @var string */
    protected $gitExitCommand;

    /**
     * @param string $message
     * @param null   $gitExitCommand
     */
    public function __construct($message, $gitExitCommand = null)
    {
        parent::__construct($message, 1);

        $this->gitExitCommand = $gitExitCommand;
    }

    /**
     * @return string
     */
    public function getGitExitCommand()
    {
        return $this->gitExitCommand;
    }
}