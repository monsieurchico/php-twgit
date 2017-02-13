<?php

namespace NMR\Workflow;

use NMR\Config\Config;
use NMR\Connector\ConnectorAwareTrait;
use NMR\Connector\NullConnector;
use NMR\Shell\Git\Filter\BranchNameFilter;
use NMR\Shell\Git\Filter\MergedBranchFilter;
use NMR\Shell\Git\Filter\NoMergedBranchFilter;
use NMR\Shell\Git\Filter\RemoteBranchFilter;
use NMR\Shell\Git\Git;
use NMR\Shell\Git\GitAwareTrait;
use NMR\Shell\ShellAwareTrait;
use NMR\Exception\ConfigurationException;
use NMR\Exception\ShellException;
use NMR\Exception\WorkflowException;
use NMR\Log\LoggerAwareTrait;
use NMR\Util\TextUtil;

/**
 * Class AbstractWorkflow
 */
abstract class AbstractWorkflow
{
    use
        LoggerAwareTrait,
        GitAwareTrait,
        ShellAwareTrait,
        ConnectorAwareTrait
    ;

    const
        NAME = "command",
        TAG = 'tag',
        FEATURE = 'feature',
        RELEASE = 'release',
        HOTFIX = 'hotfix',
        DEMO = 'demo',
        ORIGIN = 'origin'
    ;

    /** @var array */
    protected $prefixes;

    /** @var string */
    protected $origin;

    /** @var string */
    protected $stable;

    /** @var string */
    protected $firstCommitMessage;

    /** @var string */
    protected $subjectFilePath;

    /** @var string */
    protected $userRepositoryRootDir;


    /** @var string */
    protected $featuresSubjectPath;

    /** @var string */
    protected $featuresSubjectFilename;

    /**
     * AbstractWorkflowCommand constructor.
     *
     * @param string $origin
     * @param string $stable
     */
    public function __construct(Config $config)
    {
        $this->config = $config;

        $this->origin = $config->get('twgit.git.origin', 'origin');
        $this->stable = $config->get('twgit.git.stable', 'stable');

        $this->prefixes = [
            self::FEATURE => $config->get('twgit.workflow.prefixes.feature', 'feature-'),
            self::RELEASE => $config->get('twgit.workflow.prefixes.release', 'release-'),
            self::HOTFIX => $config->get('twgit.workflow.prefixes.hotfix', 'hotfix-'),
            self::DEMO => $config->get('twgit.workflow.prefixes.demo', 'demo-'),
            self::TAG => $config->get('twgit.workflow.prefixes.tag', 'v'),
            self::ORIGIN => $this->origin . '/',
        ];

        $this->firstCommitMessage = $config->get('twgit.commit.first_commit_message', '[twgit] Init %s %s %s');
        $this->featuresSubjectFilename = $config->get('twgit.features.subject_filename', '.twgit_features_subject');
        $this->setConnector(new NullConnector());
    }

    /**
     * @return Config
     */
    public function getConfig()
    {
        return $this->config;
    }

    // ---------------------------

    /**
     * @return string
     */
    protected function getUserRepositoryRootDir()
    {
        if (empty($this->userRepositoryRootDir)) {
            $this->userRepositoryRootDir = $this->execGitCommand([
                'git rev-parse --show-toplevel'
            ], true)->getOutputLastLine();
        }

        return $this->userRepositoryRootDir;
    }

    /**
     * @return string
     */
    protected function getFeaturesSubjectPath()
    {
        if (empty($this->featuresSubjectPath)) {
            $this->featuresSubjectPath = sprintf('%s/.twgit/%s',
                $this->getUserRepositoryRootDir(),
                $this->featuresSubjectFilename
            );
        }
        return $this->featuresSubjectPath;
    }

    /**
     * @throws WorkflowException
     */
    protected function push()
    {
        $branch = $this->getCurrentBranch();
        if (!$this->isType($branch, self::FEATURE)) {
            throw new WorkflowException('You must be in a feature to launch this command.');
        }

        $this->processPushBranch($branch);
    }

    /**
     * @param string $branch
     * @param string $type
     *
     * @return bool
     * @throws ConfigurationException
     */
    protected function getType($branch)
    {
        foreach ($this->prefixes as $type => $prefix) {
            if (substr($branch, 0, strlen($prefix)) === $prefix) {
                return $type;
            }
        }

        return self::TAG;
    }

    /**
     * @param string $branch
     * @param string $type
     *
     * @return bool
     * @throws ConfigurationException
     */
    protected function isType($branch, $type)
    {
        $prefix = $this->getRefNamePrefix($type);

        return substr($branch, 0, strlen($prefix)) === $prefix;
    }

    /**
     * @param string $name
     * @param string $type
     *
     * @return string
     * @throws ConfigurationException
     */
    protected function getRefName($name, $type)
    {
        return sprintf($this->getRefNamePrefix($type) . $name);
    }

    /**
     * @param string $name
     * @param string $type
     *
     * @return string
     */
    protected function cleanPrefix($name, $type)
    {
        return preg_replace(sprintf('@^%s@', $this->getRefNamePrefix($type)), '', $name);
    }

    /**
     * @param $type
     *
     * @return string
     */
    protected function getNextVersion($type)
    {
        return $this->getGit()->upgradeVersion($this->getGit()->getLastTag(), $type);
    }

    /**
     * @param $type
     *
     * @return mixed
     * @throws ConfigurationException
     */
    protected function getRefNamePrefix($type)
    {
        if (!isset($this->prefixes[$type])) {
            throw new ConfigurationException(sprintf('No prefix defined for "%s" branch.', $type));
        }

        return $this->prefixes[$type];
    }

    /**
     * @param $branch1
     * @param $branch2
     *
     * @return int
     * @throws ShellException
     */
    protected function compareBranches($branch1, $branch2)
    {
        $lastCommitBranch1 = $this->getGit()->revParse($branch1);
        $lastCommitBranch2 = $this->getGit()->revParse($branch2);

        if ($lastCommitBranch1 === $lastCommitBranch2) {
            $response = $this->execGitCommand([
                sprintf('merge-base "%s" "%s"', $lastCommitBranch1, $lastCommitBranch2)
            ], true);

            $base = $response->getOutputLastLine();
            if ($response->getReturnCode()) {
                return 4;
            }
            if ($lastCommitBranch1 === $base) {
                return 1;
            }
            if ($lastCommitBranch2 === $base) {
                return 2;
            }

            return 3;
        }

        return 0;
    }

    /**
     * @param $branch
     */
    protected function informAboutBranchStatus($branch)
    {
        $originBranch = sprintf('%s/%s', $this->origin, $branch);
        $result = $this->compareBranches($branch, $originBranch);

        switch ($result) {
            case 0:
                $this->getLogger()->help(sprintf(
                    'Local branch <help_detail>%s</><help> up-to-date with remote </><help_detail>%s</>.',
                    $branch,
                    $originBranch
                ));
                break;
            case 1:
                $this->getLogger()->help(sprintf('If need be: git merge %s', $originBranch));
                break;
            case 2:
                $this->getLogger()->help(sprintf('If need be: git push %s %s', $originBranch, $branch));
                break;
            default:
                $this->getLogger()->warning(sprintf(
                    'Branches <warning_bold>%s</> and <warning_bold>%s</> have diverged.',
                    $branch,
                    $originBranch
                ));
                $this->getLogger()->help(sprintf('If need be: git merge %s', $originBranch));
                $this->getLogger()->help(sprintf('If need be: git push %s %s', $originBranch, $branch));
                break;
        }
    }

    /**
     * @param $branch
     */
    protected function alertOldBranch($branch)
    {
        $branchFullname = $branch;
        $tagsNotMerged = $this->getTagsNotMergedIntoBranch($branchFullname);
        $nbTagsNotMerged = count($tagsNotMerged);

        if ($nbTagsNotMerged) {
            $this->getLogger()->warning(sprintf(
                '%d tags not merged into this branch: %s', $nbTagsNotMerged, implode(', ', $tagsNotMerged)
            ));
            $this->getLogger()->help(sprintf(
                'If need be: git merge --no-ff %s, then: git push %s %s',
                $this->getGit()->getLastTag(),
                $this->origin, $branch
            ));
        }
    }

    /**
     * @throws WorkflowException
     */
    protected function processFetch()
    {
        $this->execGitCommand(['fetch origin --prune']);
    }

    /**
     * @param $branch
     *
     * @throws ShellException
     */
    protected function processPushBranch($branch)
    {
        $errorMessage = sprintf('Could not push %s local branch on "%s".', $branch, $this->origin);
        $this->execGitCommand(['push --set-upstream', $this->origin, $branch], false, $errorMessage);
    }

    /**
     * @throws ShellException
     */
    protected function checkoutStable()
    {
        if (!$this->branchExists($this->stable)) {
            throw new WorkflowException(sprintf('The branch "%s" does not exist.', $this->stable));
        }

        $this->execGitCommand(['checkout', $this->stable]);
    }

    /**
     * @return string
     * @throws WorkflowException
     */
    protected function getCurrentBranch()
    {
        $response = $this->execGitCommand([
            'git branch --no-color',
        ], true);

        if ($response->getReturnCode()) {
            throw new WorkflowException('Failed to get current branch.');
        }

        foreach($response->getOutput() as $line) {
            $line = trim($line, ' ');
            if (preg_match('@^\* @', $line, $matches)) {
                return trim($line, ' *');
            }
        }

        throw new WorkflowException('Failed to get current branch.');
    }

    /**
     * @param string $tag
     *
     * @return bool
     * @throws WorkflowException
     */
    protected function tagExists($tag)
    {
        $tags = $this->getGit()->getTags();

        return in_array($tag, $tags);
    }

    /**
     * @param string $branch
     * @param bool   $remote
     *
     * @return bool
     * @throws WorkflowException
     */
    protected function branchExists($branch, $remote = false)
    {
        if ($remote) {
            $branches = $this->getGit()->getRemoteBranches();
        } else {
            $branches = $this->getGit()->getLocalBranches();
        }

        return in_array($branch, $branches);
    }

    /**
     * @param string $key
     *
     * @return string
     * @throws ShellException
     */
    protected function getGitRevParse($key)
    {
        return $this->getGit()->revParse($key, ['--verify', '-q']);
    }

    /**
     * @param $branch
     *
     * @return array
     * @throws ShellException
     */
    protected function getTagsNotMergedIntoBranch($branch)
    {
        $tagsNotMerged = [];
        $releaseRev = $this->getGitRevParse($branch);
        $allTags = $this->getGit()->getTags();

        foreach ($allTags as $tag) {
            $tagRev = current($this->execGitCommand(['rev-list', 'tags/' . $tag], true)->getOutput());
            $mergeBase = $this->execGitCommand(['merge-base', $releaseRev, $tagRev], true)->getOutputLastLine();

            if ($tagRev !== $mergeBase) {
                $tagsNotMerged[] = $tag;
            }
        }

        return $tagsNotMerged;
    }

    /**
     * @param $branch
     *
     * @throws WorkflowException
     */
    protected function assertValidRefName($branch)
    {
        $this->getLogger()->processing('Check valid ref name...');
        $this->execGitCommand(
            ['check-ref-format --branch', sprintf('"%s"', $branch)],
            true,
            sprintf('<error_bold>%s</> is not a valid reference name! See <error_bold>git check-ref-format</> for more details.', $branch)
        );
    }

    /**
     * @throws WorkflowException
     */
    protected function assertCleanWorkingTree()
    {
        $this->getLogger()->processing('Check clean working tree...');
        $result = $this
            ->execGitCommand(['status --porcelain --ignore-submodules=all'], true)
            ->getOutputLastLine();

        if (!empty($result)) {
            throw new WorkflowException(
                'Untracked files or changes to be committed in your working tree.',
                'git status'
            );
        }
    }

    /**
     * @param $branch
     *
     * @throws WorkflowException
     */
    protected function assertWorkingTreeIsNotOnDeleteBranch($branch)
    {
        $this->getLogger()->processing('Check current branch...');

        if ($this->getCurrentBranch() === $branch) {
            $this->getLogger()->processing(sprintf('Cannot delete the branch "%s" which you are currently on. So: ', $branch));
            $this->checkoutStable();
        }
    }

    /**
     * @param $branch
     *
     * @throws \NMR\Exception\ShellException
     */
    protected function assertNewLocalBranch($branch)
    {
        $this->getLogger()->processing('Check local branches...');

        if ($this->branchExists($branch, false)) {
            $this->getLogger()->processing(sprintf('Local branch "%s" already exists.', $branch));
            if (!$this->branchExists(sprintf('%s/%s', $this->origin, $branch), true)) {
                $this->getLogger()->error(sprintf('Remote feature "%s" not found while local one exists.', $branch));
                $this->getLogger()->help(sprintf(<<<EOT
<help>Perhaps :</>
<help_detail>
- check the name of your branch
- delete this out of process local branch: git branch -D {$branch}
- or force renewal if feature with "%s feature start -d xxxx"
</>
EOT
                , $this->config->get('twgit.command')));
            } else {
                $this->execGitCommand(['checkout', $branch], false, sprintf('Could not checkout "%s".', $branch));
                $this->informAboutBranchStatus($branch);
                $this->alertOldBranch($branch);
            }

            exit(0);
        }
    }

    /**
     * @return string
     * @throws WorkflowException
     */
    protected function assertTagExists()
    {
        $this->getLogger()->processing('Get last tag...');

        $lastTag = $this->getGit()->getLastTag();
        if (empty($lastTag)) {
            throw new WorkflowException('No tag exists.');
        }

        $this->getLogger()->help(sprintf('Last tag: %s', $lastTag));

        return $lastTag;
    }

    /**
     * @param $version
     *
     * @throws WorkflowException
     */
    protected function assertValidTagName($version)
    {
        $this->assertValidRefName($version);
        $this->getLogger()->processing('Check valid tag name...');

        if (!preg_match('@^[\d]+\.[\d]+\.[\d]+$@', $version)) {
            throw new WorkflowException(sprintf(
                'Unauthorized tag name: <error_bold>%s</>. Must use <major.minor.revision> format, e.g. "1.2.3".', $version
            ));
        } elseif ('0.0.0' === $version) {
            throw new WorkflowException(sprintf(
                'Forbidden tag name: <error_bold>%s</>. Must use <major.minor.revision> format, e.g. "1.2.3".', $version
            ));
        }
    }

    /**
     * @param string $version
     *
     * @throws WorkflowException
     */
    protected function assertNewAndValidTagName($version)
    {
        $this->assertValidTagName($version);
        $tag = $this->getRefName($version, self::TAG);
        $this->getLogger()->processing(sprintf('Check whether tag "%s" already exists...', $tag));

        if ($this->tagExists($version)) {
            throw new WorkflowException(sprintf('Tag <error_bold>%s</> already exists. Try <error_bold>twgit tag list</>.', $tag));
        }
    }

    /**
     * @param string     $type
     * @param string     $name
     * @param bool|false $deleteLocal
     *
     * @throws ShellException
     * @throws WorkflowException
     */
    protected function startSimpleBranch($type, $name, $deleteLocal = false)
    {
        $name = $this->cleanPrefix($name, 'release');
        $branch = $this->getRefName($name, $type);
        $remoteBranch = sprintf('%s/%s', $this->origin, $branch);

        $this->assertValidRefName($branch);
        $this->assertCleanWorkingTree();
        $this->processFetch();

        if ($deleteLocal) {
            if ($this->branchExists($branch)) {
                $this->assertWorkingTreeIsNotOnDeleteBranch($branch);
                $this->removeLocalBranch($branch);
            }
        } else {
            $this->assertNewLocalBranch($branch);
        }

        $this->getLogger()->processing(sprintf('Check remote %ss...', $type));
        if ($this->branchExists($remoteBranch, true)) {
            $this->getLogger()->processing(sprintf('Remote %s "%s/%s" detected."', $type, $this->origin, $branch));
            $this->execGitCommand(
                ['checkout --track -b ', $branch, $remoteBranch],
                false,
                sprintf('Could not check out %s "%s".', $type, $remoteBranch)
            );
        } else {
            $lastTag = $this->assertTagExists();
            $this->execGitCommand(
                ['checkout -b', $branch, 'tags/' . $lastTag],
                false,
                sprintf('Could not check out tag "%s".', $remoteBranch)
            );
            $featureSubject = $this->getFeatureSubject($branch);
            $this->processFirstCommit($branch, $type, $featureSubject);

            $this->processPushBranch($branch);
            $this->informAboutBranchStatus($branch);
        }

        $this->alertOldBranch($branch);
    }

    /**
     * @param string    $branch
     * @param string    $type
     * @param bool|true $interactive
     *
     * @throws ShellException
     * @throws WorkflowException
     */
    protected function isInitialAuthor($branch, $type, $interactive = true)
    {
        $this->getLogger()->processing('Check initial author...');
        $branchAuthor = $this->execGitCommand([
            sprintf('log %s/%s..%s/%s', $this->origin, $this->stable, $this->origin, $branch),
            '--format="%an <%ae>"', '--first-parent', '--no-merges',
            '|', 'tail -n 1'
        ], true)->getOutputLastLine();

        $currentAuthor = sprintf(
            '%s <%s>',
            $this->getGit()->getConfig('user.email'),
            $this->getGit()->getConfig('user.name')
        );

        if ($currentAuthor !== $branchAuthor) {
            $this->getLogger()->processing(sprintf(
                'Remote %s "%s/%s" was started by "%s".',
                $type, $this->origin, $branch, $branchAuthor
            ));
            if ($interactive) {
                if (!$this->getLogger()->ask('Do you want to continue ?')) {
                    throw new WorkflowException(sprintf('Warning, %s retrieving aborted.', $type));
                }
                $this->getLogger()->help('Next time, use --silent (-s) option to disable the interactive mode !');
            }
        }
    }

    /**
     * Check if current local repository has a remote origin repository configured
     * @return bool
     */
    protected function hasOriginRepository()
    {
        $response = $this->execGitCommand(['remote'], true);

        if (!$response->getReturnCode()) {
            foreach ($response->getOutput() as $line) {
                $line = trim($line);
                if ($this->origin === $line) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param string $name
     * @param string $type
     * @param string $subject
     *
     * @throws ShellException
     */
    protected function processFirstCommit($name, $type, $subject = '')
    {
        $message = trim(sprintf($this->firstCommitMessage, $type, $name, $subject));
        $this->execGitCommand(
            ['commit --allow-empty -m', sprintf('"%s"', $message)],
            false,
            'Could not make initial commit.'
        );
    }

    /**
     * @return array
     */
    protected function getReleasesInProgress()
    {
        $remoteStable = sprintf('%s/%s', $this->origin, $this->stable);
        $remoteReleasePrefix = sprintf('%s/%s', $this->origin, $this->prefixes[self::RELEASE]);

        return $this->getGit()->getBranches([
            new NoMergedBranchFilter($remoteStable),
            new RemoteBranchFilter($remoteReleasePrefix)
        ], [
            new BranchNameFilter($remoteReleasePrefix)
        ]);
    }

    /**
     * @return array
     */
    protected function getHotfixesInProgress()
    {
        $remoteStable = sprintf('%s/%s', $this->origin, $this->stable);
        $remoteReleasePrefix = sprintf('%s/%s', $this->origin, $this->prefixes[self::HOTFIX]);

        $list = [];
        foreach ($this->execGitCommand(['branch --no-color -r --no-merged', $remoteStable], true)->getOutput() as $remoteBranch) {
            $remoteBranch = trim($remoteBranch);
            if (0 === strpos($remoteBranch, $remoteReleasePrefix)) {
                $list[] = $remoteBranch;
            }
        }

        if (count($list) > 1) {
            throw new WorkflowException('One or more hotfixes are in progress. Please, clean !');
        }

        return $list;
    }

    /**
     * @return mixed
     * @throws ConfigurationException
     */
    protected function getCurrentHotfixInProgress()
    {
        $hotfixes = $this->getHotfixesInProgress();

        foreach ($hotfixes as $index => $hotfix) {
            $hotfixes[$index] = preg_replace(sprintf('@^%s/@', $this->origin), '', $hotfix);
        }

        if (count($hotfixes) > 1) {
            throw new ConfigurationException(sprintf('More than one hotfix in progress detected: <error_bold>%s</>.', implode(', ', $hotfixes)));
        }

        return current($hotfixes);
    }


    /**
     * @return mixed
     * @throws ConfigurationException
     */
    protected function getCurrentReleaseInProgress()
    {
        $releases = $this->getReleasesInProgress();

        foreach ($releases as $index => $release) {
            $releases[$index] = preg_replace(sprintf('@^%s/@', $this->origin), '', $release);
        }

        if (count($releases) > 1) {
            throw new ConfigurationException(sprintf('More than one release in progress detected: <error_bold>%s</>.', implode(', ', $releases)));
        }

        return current($releases);
    }

    /**
     * @param $branch
     *
     * @return array
     */
    protected function getContributors($branch)
    {
        $contributors = [];
        foreach ($this->execGitCommand([
            'shortlog -nse', sprintf('%s/%s..%s', $this->origin, $this->stable, $branch)
        ], true)->getOutput() as $line) {
            $line = trim($line);

            list($commits, $author) = explode("\t", $line);
            $contributors[intval($commits)] = $author;
        }

        return $contributors;
    }

    /**
     * @param null $branch
     *
     * @throws WorkflowException
     */
    protected function displayRankContributors($branch = null)
    {
        if (empty($branch)) {
            $branch = $this->getCurrentBranch();
        }

        $contributors = $this->getContributors($branch);
        krsort($contributors);

        $data = [];
        foreach ($contributors as $commits => $author) {
            $data[] = [$commits, $author];
        }

        $this->getLogger()->table($data, ['nb commits', 'author']);
    }

    /**
     * @param string $type
     * @param string $release
     *
     * @return array
     */
    protected function getFeatures($type = null, $release = null)
    {
        $list = [];

        if (empty($type) && empty($release)) {
            $list = $this->execGitCommand([
                'branch --no-color -r',
            ], true)->getOutput();
        } elseif ('merged' === $type && !empty($release)) {
            $list = $this->execGitCommand([
                'branch --no-color -r --merged', $release,
            ], true)->getOutput();
        } elseif (empty($release)) {
            $list = $this->execGitCommand([
                'branch --no-color -r --no-merged', sprintf('%s/%s', $this->origin, $this->stable),
            ], true)->getOutput();
        }

        foreach ($list as $index => & $branch) {
            $branch = trim($branch, ' *');

            if (!preg_match(sprintf("@^%s/%s@", $this->origin, $this->getRefNamePrefix(self::FEATURE)), $branch)) {
                unset($list[$index]);
            }
        }

        return array_values($list);
    }

    /**
     * @param string $branch
     *
     * @return string
     */
    protected function getFeatureSubject($branch)
    {
        $subject = "";
        $branch = str_replace(sprintf('%s/', $this->origin), '', $branch);
        $issue = $this->cleanPrefix($branch, self::FEATURE);

        if (file_exists($this->getFeaturesSubjectPath())) {
            $content = explode("\n", file_get_contents($this->getFeaturesSubjectPath()));
            foreach ($content as $info) {
                $logIssue = substr($info, 0, strpos($info, ';'));
                $logTitle = TextUtil::sanitize(substr($info, strpos($info, ';') + 1));

                if ($logIssue === $issue) {
                    return $logTitle;
                }
            }
        }

        if (empty($subject) && $this->getConnector()) {
            $subject = TextUtil::sanitize($this->getConnector()->getIssueTitle($issue));
            file_put_contents($this->getFeaturesSubjectPath(), sprintf('%s;%s', $issue, $subject) . "\n", FILE_APPEND);
        }

        return $subject;
    }

    /**
     * @param string $branch
     */
    protected function displayFeatureSubject($branch)
    {
        $subject = $this->getFeatureSubject($branch);
        if (!empty($subject)) {
            $this->getLogger()->log('feature_subject', $subject);
        }
    }

    /**
     * @param array  $branches
     * @param string $type
     *
     * @throws WorkflowException
     */
    protected function displayBranches(array $branches, $type)
    {
        $currentBranch = $this->getCurrentBranch();

        foreach ($branches as $branch) {
            $this->getLogger()->log('b', sprintf('%s: %s/%s', ucfirst($type), $this->origin, $branch), false);

            if (self::FEATURE === $type && $currentBranch === $branch) {
                $this->getLogger()->info('*', false);
            }
            $stableOrigin = $this->execGitCommand([
                'describe --abbrev=0', $branch,
            ], true)->getOutputLastLine();

            if (!empty($stableOrigin)) {
                $this->getLogger()->log('help', sprintf(' (from <help_detail>%s</>)', $stableOrigin), false);
            }
            $this->getLogger()->info('');

            if (self::FEATURE === $type) {
                $this->displayFeatureSubject($branch);
            }

            $this->alertOldBranch($branch);

            foreach (array_slice($this->execGitCommand(['show', $branch, '--pretty=medium'])->getOutput(), 0, 4) as $line) {
                $line = trim($line);
                if (!empty($line) && 0 !== strpos($line, 'Merge:')) {
                    $this->getLogger()->info($line);
                }
            }
        }
    }

    /**
     * @param string $branch
     * @param string $type
     */
    protected function displaySuperBranch($branch, $type)
    {
        $this->displayBranches([$branch], $type);

        $this->getLogger()->log('b', 'Features:');

        $mergedFeatures = $this->getFeatures('merged', $branch);
        if (!empty($mergedFeatures)) {
            foreach ($mergedFeatures as $feature) {
                $this->getLogger()->info(sprintf('    - %s "%s" <g>[merged]</>',
                    $feature,
                    $this->getFeatureSubject($feature)
                ));
            }
        }

        $mergedInProgressFeatures = $this->getFeatures('merged_in_progress', $branch);
        if (!empty($mergedInProgressFeatures)) {
            foreach ($mergedInProgressFeatures as $feature) {
                $this->getLogger()->warning(sprintf('    - %s "%s" <g>[merged, then in progress]</>',
                    $feature,
                    $this->getFeatureSubject($feature)
                ));
            }
        }

        if (empty($mergedFeatures) && empty($mergedInProgressFeatures)) {
            $this->getLogger()->info('    - No such branch exists.');
        }
    }

    /**
     *
     */
    protected function alertDissidentBranches()
    {
        $dissidentBranches = [];
        $remoteBranches = $this->getGit()->getRemoteBranches();

        $checks = array_filter([$this->stable] + $this->prefixes);

        foreach ($remoteBranches as $branch) {
            $valid = false;
            foreach ($checks as $check) {
                $remoteCheck = sprintf('%s/%s', $this->origin, $check);

                $valid = 0 === strpos($branch, substr($branch, 0, strlen($remoteCheck)+1));
                if ($valid) {
                    break;
                }
            }

            if (!$valid) {
                $dissidentBranches[] = $branch;
            }
        }

        if (!empty($dissidentBranches)) {
            $this->getLogger()->warning('Following branches are out of process:');
            foreach ($dissidentBranches as $branch) {
                $this->getLogger()->info('    - ' . $branch);
            }
        }

        $allBranches = array_merge($this->getGit()->getLocalBranches(), $this->getGit()->getTags());
        $ambiguousBranches = array_filter(array_count_values($allBranches), function ($value) {
            return $value > 1;
        });

        if (!empty($ambiguousBranches)) {
            $this->getLogger()->warning('Following branches are ambiguous:');
            foreach ($ambiguousBranches as $branch => $occ) {
                $this->getLogger()->info(sprintf('    - %s <help>(%dx)</>', $branch, $occ));
            }
        }
    }

    /**
     * @param string $branch1
     * @param string $remoteBranch2
     *
     * @throws WorkflowException
     */
    protected function assertBranchesEqual($branch1, $remoteBranch2)
    {
        $this->getLogger()->processing(sprintf('Compare branches "%s" with "%s"...', $branch1, $remoteBranch2));

        if (!$this->branchExists($branch1)) {
            throw new WorkflowException(sprintf(
                'Local branch <error_bold>%s</> does not exist and is required.',
                $branch1
            ));
        }

        if (!$this->branchExists($remoteBranch2, true)) {
            throw new WorkflowException(sprintf(
                'Remote branch <error_bold>%s</> does not exist and is required.',
                $branch1
            ));
        }

        $result = $this->compareBranches($branch1, $remoteBranch2);

        if ($result > 0) {
            $this->getLogger()->warning(sprintf(
                'Branches <warning_bold>%s</> and <warning_bold>%s</> have diverged.',
                $branch1, $remoteBranch2
            ));

            if (1 === $result) {
                $this->getLogger()->warning(sprintf(
                    'And local branch <warning_bold>%s</> may be fast-forwarded',
                    $branch1
                ));

                $this->execGitCommand(['checkout', $branch1], false, sprintf(sprintf('Checkout "%s" failed.', $branch1)));

                $this->execGitCommand(['merge', $remoteBranch2], false, sprintf(sprintf('Update "%s" failed.', $branch1)));
            } elseif (2 === $result) {
                $this->getLogger()->warning(sprintf(
                    'And local branch <warning_bold>%s</> is ahead of <warning_bold>%s</>.',
                    $branch1, $remoteBranch2
                ));
            } else {
                throw new WorkflowException('Branches need merging first.');
            }
        }
    }

    /**
     * @throws WorkflowException
     */
    protected function assertCleanStableBranchAndCheckout()
    {
        $remoteStable = sprintf('%s/%s', $this->origin, $this->stable);
        $this->execGitCommand(['checkout', $this->stable], sprintf('Could not check out "%s".', $this->stable));

        $this->getLogger()->processing(sprintf('Check health of "%s" branch.', $this->stable));

        $extraCommits = (int)$this->execGitCommand([
            'log', sprintf('%s..%s', $remoteStable, $this->stable), '--oneline',
            '|', 'wc -l'
        ], true)->getOutputLastLine();

        if ($extraCommits > 0) {
            throw new WorkflowException(sprintf(<<<EOT
Local <error_bold>%s</> branch is ahead of <error_bold>%s</>.
Commits on <error_bold>%s</> are out of process.
Try: <code>git checkout %s && git reset %s
EOT
                , $this->stable, $remoteStable, $this->stable, $this->stable, $remoteStable
            ));
        }

        $this->execGitCommand(['merge', $remoteStable], false, sprintf('Could not merge "%s" into "%s".', $remoteStable, $this->stable));
    }

    /**
     * @param string $branch
     *
     * @return array
     */
    protected function getMergedBranches($branch)
    {
        return $this->getGit()->getBranches([
            new MergedBranchFilter($branch),
            new RemoteBranchFilter(),
        ]);
    }

    /**
     * @param string $tag
     * @param string $comment
     */
    protected function createAndPushTag($tag, $comment)
    {
        $this->execGitCommand([
            'tag -a', $tag, '-m',
            sprintf('"%s %s"', $this->config->get('twgit.commit.prefix_commit_message'), $comment)
        ], false, sprintf('Could not create tag <error_bold>%s</>.', $tag));

        $this->execGitCommand([
            'push --tags', $this->origin, $this->stable
        ], false, sprintf('Could not push "%s" on "%s".', $this->stable, $this->origin));
    }

    /**
     * @param string $branch
     *
     * @throws WorkflowException
     */
    protected function removeBranch($branch)
    {
        $this->assertValidRefName($branch);
        $this->assertCleanWorkingTree();
        $this->assertWorkingTreeIsNotOnDeleteBranch($branch);

        $this->processFetch();
        $this->removeLocalBranch($branch);
        $this->removeRemoteBranch($branch);
    }

    /**
     * @param string $branch
     */
    protected function removeLocalBranch($branch)
    {
        if ($this->branchExists($branch, false)) {
            $this->execGitCommand([
                'branch -D', $branch
            ], false, sprintf('Remove local branch "%s" failed.', $branch), false);
        } else {
            $this->getLogger()->processing(sprintf('Local branch "%s" not found.', $branch));
        }
    }

    /**
     * @param string $branch
     *
     * @throws WorkflowException
     */
    protected function removeRemoteBranch($branch)
    {
        $remoteBranch = sprintf('%s/%s', $this->origin, $branch);
        if ($this->branchExists($remoteBranch, true)) {
            $this->execGitCommand([
                'push', $this->origin , sprintf(':%s', $branch)
            ], false, sprintf('Delete remote branch "%s" failed.', $remoteBranch), false);
        } else {
            $this->getLogger()->warning(sprintf('Remote branch "%s" not found.', $branch));
        }
    }

    /**
     * @param string $version
     */
    protected function createProjectVersion($version)
    {
        $this->getLogger()->processing(sprintf(
            'Create version "%s" in %s project"...',
            $version,
            ucfirst(strtolower($this->getConnector()->getName()))
        ), false);

        if ($this->getConnector()->createProjectVersion($version)) {
            $this->getLogger()->log('g', 'OK');
        } else {
            $this->getLogger()->warning('FAILED');
        }
    }

    /**
     * Check if twgit is initialized by checking if config exists
     *
     * @return bool
     */
    protected function isTwgitInitialized()
    {
        $this->config = Config::create(getenv('HOME'), $this->git->getProjectRootDir());
        $configDir = sprintf($this->config->get(sprintf('twgit.protected.project.config_dir')));
        $configFile = sprintf('%s/%s', $configDir, $this->config->get('twgit.protected.config_file'));

        return file_exists($configFile);
    }
}