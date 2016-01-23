<?php

namespace NMR\Shell;

/**
 * Class Response
 */
class Response
{
    /** @var int */
    protected $returnCode;

    /** @var array */
    protected $output;

    /** @var string */
    protected $outputLastLine;

    /** @var string */
    protected $command;

    /**
     * @param int    $returnCode
     * @param array  $output
     * @param string $outputLastLine
     * @param string $command
     */
    public function __construct($returnCode, array $output = [], $outputLastLine, $command)
    {
        $this->returnCode = $returnCode;
        $this->output = $output;
        $this->outputLastLine = trim($outputLastLine);
        $this->command = $command;
    }

    /**
     * @return int
     */
    public function getReturnCode()
    {
        return $this->returnCode;
    }

    /**
     * @return array
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * @return string
     */
    public function getOutputLastLine()
    {
        return $this->outputLastLine;
    }

    /**
     * @return string
     */
    public function getCommand()
    {
        return $this->command;
    }

    /**
     * @return string
     */
    public function getOutputAsString()
    {
        return implode("\n", $this->output);
    }
}