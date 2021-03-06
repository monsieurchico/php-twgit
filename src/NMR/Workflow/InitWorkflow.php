<?php

namespace NMR\Workflow;

use NMR\Application;
use NMR\Config\Config;
use NMR\Exception\ConfigurationException;
use NMR\Exception\WorkflowException;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Class InitWorkflow
 */
class InitWorkflow extends AbstractWorkflow
{

    /**
     * @param InputInterface $input
     *
     * @throws WorkflowException
     */
    public function defaultAction(InputInterface $input)
    {
        $tagName = $input->getArgument('tagname');
        if (null !== $input->getArgument('remoteUrl')) {
            $remoteUrl = $input->getArgument('remoteUrl');
        } else {
            $remoteUrl = null;
        }

        $this->logger->processing('Check need for git init...');
        if (!$this->git->isInGitRepo()) {
            $this->execGitCommand(['git', 'init'], false, 'Initialization of git repository failed!');
        } else {
            $this->assertCleanWorkingTree();
        }

        $this->assertNewAndValidTagName($tagName);

        $this->logger->processing(sprintf('Check presence of remote "%s" repository...', $this->origin));

        if(!$this->hasOriginRepository()) {
            if (null === $remoteUrl) {
                throw new WorkflowException(sprintf('Remote \'%s\' repository url required!', $this->origin));
            }
            $this->execGitCommand(['remote add', $this->origin, $remoteUrl], true, 'Add remote repository failed!');
        }
        $this->processFetch();

        $this->logger->processing(sprintf('Check presence of \'%s\' branch...', $this->stable));

        /*
         * If local 'stable' branch exists, push it to remote if needed
         * Else if remote 'stable' branch exists, pull it to local
         * Else, create 'stable' from 'master'
         */
        if ($this->branchExists($this->stable, false)) {
            $this->logger->processing(sprintf('Local \'%s\' detected.', $this->stable));
            if (!$this->branchExists($this->stable, true)) {
                $this->execGitCommand(['push', '--set-upstream', $this->origin, $this->stable], true, 'Git push failed!');
            }
        } elseif ($this->branchExists($this->stable, true)) {
            $this->logger->processing(sprintf('Remote \'%s/%s\' detected.', $this->origin, $this->stable));
            $this->execGitCommand(
                ['checkout', '--track', '-b', $this->stable, sprintf('%s/%s', $this->origin, $this->stable)],
                true,
                sprintf('Could not checkout \'%s/%s\'!', $this->origin, $this->stable));
        } else {
            if ($this->branchExists('master', true)) {
                $this->execGitCommand(
                    ['checkout', '-b', $this->stable, sprintf('%s/master', $this->origin)],
                    true,
                    sprintf('Could not check out \'%s/master\'!', $this->origin));
            } elseif ($this->branchExists('master', false)) {
                $this->execGitCommand(
                    ['checkout', '-b', $this->stable, 'master'],
                    true,
                    sprintf('Create local \'%s\' branch failed!', $this->stable));
            } else {
                $this->processFirstCommit('branch', 'stable');
                $this->execGitCommand(['branch', '-m', $this->stable], true, 'Rename of master branch failed!');
            }
            $this->processPushBranch($this->stable);
        }

        $this->initGitignore();

        $this->createAndPushTag($this->getRefName($tagName, self::TAG), 'First tag.');
    }

    /**
     * Initialize .gitignore file
     * @return $this
     */
    private function initGitignore()
    {
        $projectDir = $this->config->get('twgit.protected.project.root_dir');
        $fileName = $projectDir . DIRECTORY_SEPARATOR . '.gitignore';
        if (!file_exists($fileName)) {
            $file = fopen($projectDir . DIRECTORY_SEPARATOR . '.gitignore', 'a+');
            fwrite($file, '.twgit' . PHP_EOL);
            $this->execGitCommand(['add', '.gitignore'], true, 'Add minimal .gitignore failed!');
            $this->execGitCommand(['commit', '-m', '"Add minimal .gitignore"'], true, 'Add minimal .gitignore failed!');
            $this->execGitCommand(['push', $this->origin, $this->stable], true, 'Add minimal .gitignore failed!');
        }
        return $this;
    }


}