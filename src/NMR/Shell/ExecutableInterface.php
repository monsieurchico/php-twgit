<?php

namespace NMR\Shell;

/**
 * Interface ExecutableInterface
 */
interface ExecutableInterface
{
    /**
     * @param array $chunks
     *
     * @return string
     */
    function buildCommand(array $chunks);

    /**
     * @param string $commad
     * @param null   $errorMessage
     *
     * @return Response
     */
    function execSilentCommand($commad, $errorMessage = null);

    /**
     * @param string     $command
     * @param null       $errorMessage
     * @param bool|false $silent
     *
     * @return Response
     */
    function execCommand($command, $errorMessage = null, $silent = false);
}