<?php

namespace NMR\Shell;

use NMR\Exception\ConfigurationException;
use NMR\Exception\ShellException;
use NMR\Log\LoggerAwareTrait;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Shell
 */
class Shell implements ExecutableInterface
{
    use LoggerAwareTrait;

    /** @var bool */
    protected $verbose;

    /**
     * Shell constructor.
     *
     * @param bool $verbose
     */
    public function __construct($verbose = false)
    {
        $this->verbose = $verbose;
    }

    /**
     * @return string
     */
    protected function getPrompt()
    {
        return '#shell';
    }

    /**
     * @param string    $command
     * @param null      $errorMessage
     * @param bool|true $abortOnError
     *
     * @return Response
     * @throws ShellException
     */
    public function execSilentCommand($command, $errorMessage = null, $abortOnError = true)
    {
        return $this->execCommand($command, $errorMessage, true, $abortOnError);
    }

    /**
     * @param string     $command
     * @param null       $errorMessage
     * @param bool|false $silent
     * @param bool|true  $abortOnError
     *
     * @return Response
     * @throws ShellException
     */
    public function execCommand($command, $errorMessage = null, $silent = false, $abortOnError = true)
    {
        trim($command);
        $command = sprintf('(%s) 2>&1', $command);

        if (!$silent || $this->verbose) {
            $this->getLogger()->log('code', trim(sprintf('%s %s', $this->getPrompt(), $command)));
        }

        $lastLine = exec($command, $output, $res);

        if (!is_array($output)) {
            $output = [];
        }

        $response = new Response($res, $output, $lastLine, $command);

        if ($abortOnError && $response->getReturnCode()) {
            throw new ShellException(
                empty($errorMessage) ? $response->getOutputAsString() : $errorMessage,
                $response->getReturnCode()
            );
        }

        return $response;
    }

    /**
     * @param array $chunks
     *
     * @return string
     */
    public function buildCommand(array $chunks)
    {
        $command = '';
        foreach ($chunks as $c) {
            $command .= ' ' . $c;
            trim($command);
        }
        return trim($command);
    }
}