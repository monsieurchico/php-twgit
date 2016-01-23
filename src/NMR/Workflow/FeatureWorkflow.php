<?php

namespace NMR\Workflow;

use NMR\Exception\ShellException;
use NMR\Exception\WorkflowException;
use Symfony\Component\Console\Input\InputInterface;
use NMR\Shell\Shell;

/**
 * Class FeatureWorkflow
 */
class FeatureWorkflow extends AbstractWorkflow
{
    /**
     * @param InputInterface $input
     */
    public function startAction(InputInterface $input)
    {
        $this->startSimpleBranch(
            'feature',
            $input->getArgument('name'),
            $input->getOption('delete')
        );
    }

    /**
     * @param InputInterface $input
     */
    public function committersAction(InputInterface $input)
    {
        $this->displayRankContributors($input->getArgument('name'));
    }

    /**
     * @param InputInterface $input
     */
    public function mergeIntoReleaseAction(InputInterface $input)
    {
        $this->assertCleanWorkingTree();
        $this->processFetch();

        $this->getLogger()->processing('Check remote release...');
        $release = $this->getCurrentReleaseInProgress();

        if (empty($release)) {
            throw new WorkflowException('No release in progress.');
        }

        $feature = $input->getArgument('name');
        $interactive = !$input->getOption('silent');

        if (empty($feature)) {

            $feature = $this->getCurrentBranch();

            if (!$this->isType($feature, self::FEATURE)) {
                throw new WorkflowException('You must be in a feature if you didn\'t specify one.');
            } else {
                if ($interactive) {
                    if (!$this->getLogger()->ask(sprintf('Are you sure to merge "%s" into "%s" ?', $feature, $release))) {
                        throw new WorkflowException('Merge into current release aborted.');
                    }
                }
            }
        } else {
            $feature = $this->getRefName($this->cleanPrefix($feature, self::FEATURE), self::FEATURE);
        }

        $this->mergeFeatureIntoBranch($feature, $release);

        $this->setIssueFixVersion(
            $this->cleanPrefix($feature, self::FEATURE),
            $this->cleanPrefix($release, self::RELEASE)
        );
    }

    /**
     * @param string $feature
     * @param string $destBranch
     *
     * @throws WorkflowException
     */
    protected function mergeFeatureIntoBranch($feature, $destBranch)
    {
        $commands = [];
        $this->getLogger()->processing('Check remote feature...');
        if (!$this->branchExists(sprintf('%s/%s', $this->origin, $feature), true)) {
            throw new WorkflowException(sprintf(
                'Remote feature <error_bold>%s/%s</> not found.',
                $this->origin,
                $feature
            ));
        }

        $commands[] = [
            'shell' => $this->getShell(),
            'command' => $this->getShell()->buildCommand(['twgit feature start', $this->cleanPrefix($feature, self::FEATURE)])
        ];
        $commands[] = [
            'git' => $this->getShell(),
            'command' => $this->getGit()->buildCommand(['pull', $this->origin, $feature])
        ];

        $type = $this->getType($destBranch);

        if (preg_match(sprintf('@^%s|%s|%s$@', self::RELEASE, self::HOTFIX, self::DEMO), $type)) {
            $commands[] = [
                'shell' => $this->getShell(),
                'command' => sprintf('twgit %s start', $type)
            ];
        } else {
            throw new WorkflowException(sprintf(
                'The destination branch "%s" must be of type release, hotfix or demo.', $destBranch
            ));
        }

        $commands[] = [
            'git' => $this->getShell(),
            'command' => $this->getGit()->buildCommand(['merge --no-ff', $feature])
        ];

        $commands[] = [
            'git' => $this->getShell(),
            'command' => $this->getGit()->buildCommand(['push', $this->origin, $destBranch])
        ];

        /**
         * @var Shell $shell
         * @var string $command
         */
        $nbCommands = count($commands);
        foreach ($commands as $command) {
            extract($command);
            $response = $shell->execCommand($command);

            if ($response->getReturnCode()) {
                $this->getLogger()->error(sprintf(
                    'Merge <error_bold>%s</> into <error_bold>%s</> aborted.', $feature, $destBranch
                ));
                $this->getLogger()->help('Command not executed: ');
                $this->getLogger()->info(sprintf('<help_detail>%s</>', $command));
                if (preg_match('@merge@', $command)) {
                    $this->getLogger()->help(sprintf('<help_detail>  - resolve conflicts</>', $command));
                    $this->getLogger()->help(sprintf('<help_detail>  - resolve conflicts</>', $command));
                    $this->getLogger()->help(sprintf('<help_detail>  - git add...</>', $command));
                    $this->getLogger()->help(sprintf('<help_detail>  - git commit...</>', $command));
                }
                exit(0);
            }
        }
    }

    /**
     * @param string $issue
     * @param string $version
     */
    protected function setIssueFixVersion($issue, $version)
    {
        $this->getLogger()->processing(sprintf('Affect version "%s" to issue "%s"...', $version, $issue), false);

        if ($this->getConnector()->setIssueFixVersion($issue, $version)) {
            $this->getLogger()->log('g', 'OK');
        } else {
            $this->getLogger()->warning('FAILED');
        }
    }
}