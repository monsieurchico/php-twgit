<?php

namespace NMR\Workflow;

use NMR\Exception\ConfigurationException;
use NMR\Exception\WorkflowException;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Class ReleaseWorkflow
 */
class ReleaseWorkflow extends AbstractWorkflow
{
    const
        DEFAULT_VERSION_UPGRADE_DIGIT = 'minor';

    /**
     * @param InputInterface $input
     *
     * @throws ConfigurationException
     * @throws WorkflowException
     */
    public function startAction(InputInterface $input)
    {
        $version = $this->cleanPrefix($input->getArgument('name'), self::RELEASE);

        $this->assertCleanWorkingTree();
        $this->processFetch();
        $this->assertTagExists();

        $currentRelease = $this->getCurrentReleaseInProgress();

        if (empty($version)) {
            if (!empty($currentRelease)) {
                $version = $currentRelease;
            } else {
                $version = $this->getNextVersion(
                    $input->getOption('major') ? 'major' : ($input->getOption('minor') ? 'minor' : self::DEFAULT_VERSION_UPGRADE_DIGIT)
                );

                $this->getLogger()->info('Release: ' . $version);

                if (!$input->getOption('silent')) {
                    if (!$this->getLogger()->ask('Do you want to continue ?')) {
                        throw new WorkflowException('New release aborted.');
                    }
                    $this->getLogger()->help('Next time, use --silent (-s) option to disable the interactive mode !');
                }
            }
        }

        $version = $this->cleanPrefix($version, self::RELEASE);
        $release = $this->getRefName($version, self::RELEASE);
        $this->assertNewAndValidTagName($version);

        if (!empty($currentRelease)) {
            if ($currentRelease !== $release) {
                throw new WorkflowException(sprintf(
                    'No more one release is authorized at the same time! Try: "%s release list" or "%s release start %s"',
                    $this->config->get('twgit.command'), $this->config->get('twgit.command'), $currentRelease
                ));
            } else {
                $remoteBranch = sprintf('%s/%s', $this->origin, $release);
                $this->isInitialAuthor($release, self::RELEASE, !$input->getOption('silent'));
                $this->assertNewLocalBranch($release);
                $this->execGitCommand([
                    'checkout --track -b', $release, $remoteBranch
                ], false, sprintf('Could not check out release "%s".', $remoteBranch));
            }
        } else {
            $lastTag = $this->getGit()->getLastTag();
            $this->execGitCommand([
                'checkout -b', $release, sprintf('tags/%s', $lastTag)
            ], false, sprintf('Cound not check out tag "%s".', $lastTag));

            $this->processFirstCommit($release, self::RELEASE, '');
            $this->processPushBranch($release);
        }

        $this->alertOldBranch($release);

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
            throw new WorkflowException('You must specify a name for the release to remove.');
        }

        $version = $this->cleanPrefix($version, self::RELEASE);
        $branch = $this->getRefName($version, self::RELEASE);

        $this->getLogger()->processing(sprintf('Remove release "%s"...', $branch));
        $this->removeBranch($branch);
    }

    /**
     * @param InputInterface $input
     *
     * @throws ConfigurationException
     */
    public function listAction(InputInterface $input)
    {
        if (!$input->getOption('no-fetch')) {
            $this->processFetch();
        }

        $releases = $this->execGitCommand([
            'branch --no-color -r --merged', sprintf('%s/%s', $this->origin, $this->stable),
            '|', sprintf('grep "%s/%s"', $this->origin, self::RELEASE),
            '|', "sed 's/^[* ]*//'",
            '|', sprintf("sed 's/%s\\///g'", $this->origin)
        ], true)->getOutput();

        if (!empty($releases)) {
            $this->getLogger()->help(sprintf('Remote releases merged into <help_detail>%s</>:', $this->stable));
            $this->getLogger()->help(sprintf(
                'A release must be deleted after merge into <help_detail>"%s"</>.' . "\n" .
                'Following releases should no exists.',
                $this->stable
            ));
            $this->displayBranches($releases, self::RELEASE);
        }

        $release = $this->getCurrentReleaseInProgress();
        $this->getLogger()->info("");
        $this->getLogger()->help(sprintf('Remote releases NOT merged into <help_detail>%s</>:', $this->stable));

        if (!empty($release)) {
            $this->displaySuperBranch($release, self::RELEASE);
        }

        $this->alertDissidentBranches();
    }

    /**
     * @param InputInterface $input
     *
     * @throws WorkflowException
     */
    public function pushAction(InputInterface $input)
    {
        $currentBranch = $this->getCurrentBranch();
        $remoteReleases = $this->getReleasesInProgress();

        if ($currentBranch !== $remoteReleases) {
            throw new WorkflowException('You must be in a release to launch this command.');
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
        if (!$input->getOption('no-fetch')) {
            $this->processFetch();
        }

        $currentRelease = $this->getCurrentReleaseInProgress();
        if (empty($currentRelease)) {
            throw new WorkflowException('No release in progress.');
        }

        $this->getLogger()->processing(sprintf('Remote release "%s" detected.', $currentRelease));
        $tag = $this->cleanPrefix($currentRelease, self::RELEASE);

        $this->assertValidTagName($tag);

        $this->getLogger()->processing('Check hotfix in progress...');
        $hotfixes = $this->getHotfixesInProgress();

        if (!empty($hotfixes)) {
            $firstHotfix = current($hotfixes);
            throw new WorkflowException(sprintf(
                'Close a release while hotfix in progress is forbidden. Hotfix "%s" must be closed first.',
                $firstHotfix
            ));
        }

        $this->getLogger()->processing('Check tags not merged...');
        $tagsNotMerged = $this->getTagsNotMergedIntoBranch($currentRelease);

        if (!empty($tagsNotMerged)) {
            $this->getLogger()->error('You must merge the last tag into this release before close it.');
            $this->getLogger()->help(sprintf(
                'In %s branch: git merge --no-ff %s, then: git push %s%s.',
                $currentRelease, end($tagsNotMerged), $this->origin, $currentRelease
            ));
            exit(-1);
        }

        $this->getLogger()->processing('Check remote features...');
        $features = $this->getFeatures('merged_in_progress', $currentRelease);

        if (!empty($features)) {
            throw new WorkflowException(sprintf(
                'Features exists that are merged into this release but yet in development: ' .
                "\n    - " .
                implode("\n    - ", $features)
            ));
        }

        $this->getLogger()->processing(sprintf('Check local branch "%s".', $currentRelease));

        if ($this->branchExists($currentRelease)) {
            $this->assertBranchesEqual($currentRelease, sprintf('%s/%s', $this->origin, $currentRelease));
        } else {
            $this->execGitCommand([
                'checkout --track -b', $currentRelease, sprintf('%s/%s', $this->origin, $currentRelease)
            ], false, sprintf('Could not check out release %s', $currentRelease));
        }

        $this->assertCleanStableBranchAndCheckout();

        $this->execGitCommand([
            'merge --no-ff', $currentRelease
        ], false, sprintf('Could not merge "%s" into "%s".', $currentRelease, $this->stable));

        $mergedFeatures = $this->getFeatures('merged', $currentRelease);

        $tagComment = sprintf('Release finish: %s', $currentRelease);
        foreach ($mergedFeatures as $feature) {
            $tagComment .= sprintf("\n" . 'Contains %s %s', $feature, $this->getFeatureSubject($feature));
        }

        $this->createAndPushTag($tag, $tagComment);

        foreach ($mergedFeatures as $remoteFeature) {
            $feature = preg_replace(sprintf("@^%s/@", $this->origin), '', $remoteFeature);
            $this->getLogger()->processing(sprintf('Delete feature "%s" (remote %s) ...', $feature, $remoteFeature));
            $this->removeBranch($feature);
        }

        $this->removeLocalBranch($currentRelease);
        $this->removeRemoteBranch($currentRelease);
    }
}