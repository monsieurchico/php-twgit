<?php

namespace NMR\Shell\Git;

use NMR\Exception\ConfigurationException;
use NMR\Exception\ShellException;
use NMR\Shell\Git\Filter\FilterableInterface;
use NMR\Shell\Git\Filter\PostFilterableInterface;
use NMR\Shell\Git\Filter\PreFilterableInterface;
use NMR\Shell\Git\Filter\RemoteBranchFilter;
use NMR\Shell\Response;
use NMR\Shell\Shell;

/**
 * Class Git
 */
class Git extends Shell
{
    const
        VERSION_EQUALS = 0,
        VERSION_PREVIOUS = 1,
        VERSION_NEXT = 2
    ;

    private $projectRootDir;
    private $isInsideWorkTree;

    /**
     * @return string
     */
    protected function getPrompt()
    {
        return '#git';
    }

    /**
     * @param $config
     *
     * @return string
     * @throws ShellException
     */
    public function getConfig($config)
    {
        return $this->execSilentCommand($this->buildCommand(['config', $config]))->getOutputLastLine();
    }

    /**
     * @return string
     */
    public function getLastTag()
    {
        $tags = $this->getTags();

        return end($tags);
    }


    public function getBranches(array $preFilters = [], array $postFilters = [])
    {
        $command = $this->buildCommand(['branch --no-color']);

        foreach ($preFilters as $filter) {
            if ($filter instanceOf PreFilterableInterface) {
                $command .= ' ' . $filter->toString();
            }
        }

        $response = $this->execSilentCommand($command);

        if ($response->getReturnCode()) {
            throw new ShellException(sprintf(
                'Failed to retrieve %s branches.',
                $remote ? 'remote' : 'local'
            ));
        }

        $branches = $response->getOutput();

        foreach ($branches as $index => & $branch) {
            $branch = trim($branch, ' *');
            if (false !== strpos($branch, 'HEAD')) {
                unset($branches[$index]);
            }
        }

        foreach ($postFilters as $filter) {
            if ($filter instanceOf PostFilterableInterface) {
                $filter->apply($branches);
            }
        }

        return $branches;
    }

    /**
     * @return array
     * @throws ShellException
     */
    public function getTags()
    {
        $tags = [];
        $response = $this->execSilentCommand('tag');

        foreach ($response->getOutput() as $t) {
            $tag = $this->sanitizeTagName($t);
            $tags[$this->convertTagToInt($tag)] = $tag;
        }

        ksort($tags);

        return $tags;
    }

    /**
     * @return array
     * @throws ShellException
     */
    public function getLocalBranches()
    {
        return $this->getBranches();
    }

    /**
     * @return array
     * @throws ShellException
     */
    public function getRemoteBranches()
    {
        return $this->getBranches([new RemoteBranchFilter()]);
    }

    /**
     * @param string     $branch
     * @param bool|false $remote
     *
     * @return Response
     * @throws ShellException
     */
    public function removeBranch($branch, $remote = false)
    {
        if ($remote) {
            $response = $this->execCommand($this->buildCommand([
                'push origin', ':' . $branch
            ]));
        } else {
            $response = $this->execCommand($this->buildCommand([
                'branch -D', $branch
            ]));
        }

        if ($response->getReturnCode()) {
            throw new ShellException(sprintf(
                'Failed to remove %s branch "%s".',
                $remote ? 'remote' : 'local',
                $branch
            ));
        }

        return $response;
    }

    /**
     * @param string $branch
     * @param array  $options
     *
     * @throws ShellException
     */
    public function revParse($branch, array $options = ['-q', '--verify'])
    {
        return $this->execSilentCommand($this->buildCommand(array_merge([
            'rev-parse'
        ], $options, [$branch])))->getOutputLastLine();
    }

    /**
     * @param string $currentTag
     * @param string $upgradeType
     *
     * @return string
     * @throws ConfigurationException
     */
    public function upgradeVersion($currentTag, $upgradeType = 'revision')
    {
        $intVersion = $this->convertTagToInt($currentTag);
        $chunks = explode('.', $currentTag);

        switch ($upgradeType) {
            case 'major':
                $factor = pow(1000, 2);
                break;
            case 'minor':
                $factor = pow(1000, 1);
                break;
            case 'revision':
                $factor = pow(1000, 0);
                break;
            default:
                throw new ConfigurationException(sprintf('Unknown upgrade type "%s".', $upgradeType));
        }

        return $this->convertIntToTag((intval($intVersion / $factor) + 1) * $factor);
    }

    /**
     * @param string $version1
     * @param string $version2
     *
     * @return int
     */
    public function compareVersions($version1, $version2)
    {
        $intVersion1 = $this->convertTagToInt($version1);
        $intVersion2 = $this->convertTagToInt($version2);

        if ($intVersion1 === $intVersion2) {
            return self::VERSION_EQUALS;
        }

        if ($intVersion1 < $intVersion2) {
            return self::VERSION_PREVIOUS;
        }

        return self::VERSION_NEXT;
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
        return parent::execCommand($this->decorateWithGitCommand($command), $errorMessage, $silent, $abortOnError);
    }

    /**
     * @param array $chunks
     *
     * @return string
     */
    public function buildCommand(array $chunks)
    {
        $command = parent::buildCommand($chunks);

        return $this->decorateWithGitCommand($command);
    }

    /**
     * @param $command
     *
     * @return string
     */
    protected function decorateWithGitCommand($command)
    {
        trim($command);

        if ('git' !== strtolower(substr($command, 0, 3))) {
            $command = 'git ' . $command;
        }

        return $command;
    }

    /**
     * @param $tag
     * @return int
     *
     * @example
     *  1.1.1 =>     1001001
     * 10.234.34 => 10234034
     */
    public function convertTagToInt($tag)
    {
        $n = 0;
        $digits = explode('.', $tag);
        if (count($digits) <> 3) {
            $this->getLogger()->error(sprintf(
                'Invalid tag "%s": it does not support format "major.minor.revision".',
                $tag
            ));
            exit(-1);
        }

        for ($i = 2 ; $i >= 0 ; $i--) {
            $n += pow(1000, $i) * intval($digits[2 - $i]);
        }

        return $n;
    }

    /**
     * @param int $int
     * @return string
     *
     * @example
     *   1001001 => 1.1.1
     *  10234034 => 10.234.34
     */
    public function convertIntToTag($int)
    {
        $tags = [];
        for ($i = 2 ; $i >= 0 ; $i--) {
            $pow = pow(1000, $i);
            $v = intval($int / $pow);
            $int -= ($v * $pow);

            $tags[] = $v;
        }

        return implode('.', $tags);
    }

    /**
     * @param string $tag
     *
     * @return string
     * @example
     *  v1.1.1 => 1.1.1
     *  1.1.3-pouet => 1.1.3
     *  1.2 => 1.2.0
     */
    protected function sanitizeTagName($tag)
    {
        $tag = preg_replace('@[^\d\.]+@', '', $tag);
        $nbDots = substr_count($tag, '.');
        if ($nbDots < 2) {
            for ($i = 0 ; $i < 2 ; $i++) {
                $tag .= '.0';
            }
        }

        return $tag;
    }

    /**
     * Check if actually in a git repository
     * @return bool
     */
    public function isInGitRepo() {

        if (!isset($this->isInsideWorkTree)) {
            $this->isInsideWorkTree = ('true' === $this->execCommand(
                    $this->buildCommand(['rev-parse', '--is-inside-work-tree', '2> /dev/null']),
                    '',
                    true,
                    false
                )->getOutputLastLine());
        }
        return $this->isInsideWorkTree;
    }

    /**
     * Get project root dir
     * @return string
     */
    public function getProjectRootDir()
    {
        if (!isset($this->projectRootDir)) {
            $this->projectRootDir = realpath($this->revParse('--git-dir', array()) . DIRECTORY_SEPARATOR . '..');
        }
        return $this->projectRootDir;
    }
}