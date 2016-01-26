<?php

namespace NMR\Workflow;

use NMR\Exception\ConfigurationException;
use NMR\Exception\WorkflowException;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Class HotfixWorkflow
 */
class HotfixWorkflow extends AbstractWorkflow
{
    const
        DEFAULT_VERSION_UPGRADE_DIGIT = 'revision';

    /**
     * @param InputInterface $input
     *
     * @throws ConfigurationException
     * @throws WorkflowException
     */
    public function startAction(InputInterface $input)
    {
        $this->assertCleanWorkingTree();
        $this->processFetch();

        $this->getLogger()->processing('Check remote hotfixes...');
        $remoteHotfixes = $this->getHotfixesInProgress();

        $currentBranch = $this->getCurrentBranch();

        if (empty($remoteHotfixes)) {
            $this->assertTagExists();
            $lastTag = $this->getGit()->getLastTag();
            $version = $this->getNextVersion(self::DEFAULT_VERSION_UPGRADE_DIGIT);
            $hotfix = $this->getRefName($version, self::HOTFIX);

            if ($hotfix !== $currentBranch) {
                if (!$this->branchExists($hotfix, false)) {
                    $this->execGitCommand([
                        'checkout -b', $hotfix, sprintf('tags/%s', $lastTag)
                    ], false, sprintf('Could not checkout out tag "%s"', $lastTag));

                    $this->processFirstCommit($hotfix, self::HOTFIX, '');
                } else {
                    $this->execGitCommand(['checkout', $hotfix], false);
                }

                $this->processPushBranch($hotfix);
            }
        } else {
            $nbHotfixes = count($remoteHotfixes);

            if ($nbHotfixes > 1) {
                throw new WorkflowException(sprintf('You cannot have multiple hotfixes in progress at the same time !'));
            }

            $remoteHotfix = current($remoteHotfixes);
            $hotfix = str_replace(sprintf('%s/', $this->origin), '', $remoteHotfix);
            $this->getLogger()->processing(sprintf('Remote hotfix "%s" detected.', $hotfix));

            $this->assertValidRefName($hotfix);
            $this->isInitialAuthor($hotfix, self::HOTFIX, !$input->getOption('silent'));
            $this->assertNewLocalBranch($hotfix);

            $this->execGitCommand([
                'checkout --track -b', $hotfix, $remoteHotfix
            ], false, sprintf('Could not check out release "%s".', $remoteHotfix));
        }

        $this->getConnector()->createProjectVersion($version);
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
    public function removeAction(InputInterface $input)
    {
        $version = $input->getArgument('name');

        if (empty($version)) {
            throw new WorkflowException('You must specify a name for the hotfix to remove.');
        }

        $version = $this->cleanPrefix($version, self::HOTFIX);
        $branch = $this->getRefName($version, self::HOTFIX);

        $this->getLogger()->processing(sprintf('Remove hotfix "%s"...', $branch));
        $this->removeBranch($branch);
    }

    /**
     * @param InputInterface $input
     *
     * @throws WorkflowException
     */
    public function pushAction(InputInterface $input)
    {
        $currentBranch = $this->getCurrentBranch();
        $remoteHotfixes = $this->getHotfixesInProgress();
        $remoteHotfixes = str_replace(sprintf('%s/', $this->origin), '', current($remoteHotfixes));

        if ($currentBranch !== $remoteHotfixes) {
            throw new WorkflowException('You must be in a hotfix to launch this command.');
        }

        $this->processPushBranch($currentBranch);
    }

    /**
     * @param InputInterface $input
     *
     * @throws WorkflowException
     */
    public function finishAction(InputInterface $input)
    {
        $this->assertCleanWorkingTree();
        $this->processFetch();

        $this->getLogger()->processing('Check remote hotfix...');
        $hotfixes = $this->getHotfixesInProgress();

        if (empty($hotfixes)) {
            throw new WorkflowException('No hotfix in progress.');
        } elseif (count($hotfixes) > 1) {
            throw new WorkflowException('Multiple hotfixes found : remove useless ones.');
        }

        $remoteHotfix = current($hotfixes);
        $currentHotfix = $this->cleanPrefix($remoteHotfix, self::ORIGIN);
        $this->getLogger()->processing(sprintf('Remote hotfix "%s" detected.', $currentHotfix));
        $this->getLogger()->processing(sprintf('Check local branch "%s"....', $currentHotfix));

        if ($this->branchExists($currentHotfix, false)) {
            $this->assertBranchesEqual($currentHotfix, $remoteHotfix);
        } else {
            $this->execGitCommand([
                'checkout --track -b', $hotfix, $remoteHotfix
            ], false, sprintf('Could not check out hotfix "%s".', $remoteHotfix));
        }

        $tag = $this->cleanPrefix($currentHotfix, self::HOTFIX);
        $this->assertNewAndValidTagName($tag);

        $this->assertCleanStableBranchAndCheckout();

        $this->execGitCommand([
            'merge --no-ff', $currentHotfix
        ], false, sprintf('Could not merge "%s" into "%s".', $currentHotfix, $this->stable));

        $this->createAndPushTag($tag, sprintf('Hotfix finish: %s', $currentHotfix));

        $this->removeLocalBranch($currentRelease);
        $this->removeRemoteBranch($currentRelease);

        $currentRelease = $this->getCurrentReleaseInProgress();

        if (!empty($currentRelease)) {
            $this->getLogger()->warning(sprintf('Do not forget to merge "%s" tag into "%s" release before close it !' . "\n" .
            'Try on release: git merge --no-ff' . $tag, $tag, $currentRelease));
        }
    }
}