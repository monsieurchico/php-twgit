<?php

namespace NMR\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class ReleaseCommand
 */
class ReleaseCommand extends Command
{
    /**
     * {inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('release')
            ->setDescription('Manage your release branches.')
            ->addArgument('name', InputArgument::OPTIONAL, 'Name')
            ->addOption('minor', 'm', InputOption::VALUE_NONE)
            ->addOption('major', 'M', InputOption::VALUE_NONE)
            ->addOption('no-fetch', 'F', InputOption::VALUE_NONE)
        ;
    }

    /**
     * {inheritdoc}
     */
    public function needGitRepository()
    {
        return true;
    }

    /**
     * {inheritdoc}
     */
    public function needTwgitRepository()
    {
        return true;
    }

    /**
     * {inheritdoc}
     */
    public function showUsage()
    {
        $prefixRelease = $this->getConfig()->get('twgit.workflow.prefixes.release');
        $prefixTag = $this->getConfig()->get('twgit.workflow.prefixes.tag');
        $stable = $this->getConfig()->get('twgit.git.stable');

        $this->getLogger()->writeln(<<<EOT
<cb>(i)</> <c>Usage</>
<wb>    twgit release [<action>]</>

<cb>(i)</> <c>Availabe commands are:</>
    <wb>committers [-F]</>
            List all committers (authors in fact) into the current release.
            Add -F to not make fetch.

    <wb>finish</>
            Merge current release branch into '{$stable}', create a new tag and push.
            If no <tagname> is specified then current release name will be used.
            Add -s to run in non-interactive mode (always say yes).

    <wb>list [-F]</>
            List remove release with their merged features.
            Add -F to not make fetch.

    <wb>push</>
            Push current release to 'origin' repository.
            It's a shortcut for 'git push origin <branchname>'

    <wb>remove <releasename></>
            Remove both local and remote specified release branch. No feature will be removed.
            Despite that, create the same tag as finish action to clearly distinguish the next release from this one.

    <wb>start [<releasename] [-s|-M|-m]</>
            Create both a new local and remote release, or fetch the remote release, or checkout the local release.
            <releasename> must use major.minor.revision format.
            If no <releasename> is specified, a name will be generated buy incrementing the last tag (e.g. v1.2.3):
                -M for a new major version (-> {$prefixRelease}2.0.0)
                -m for a new minor version (-> {$prefixRelease}1.3.0)

    Prefix '{$prefixRelease}' will be added to <releasename> parameters.
    Prefix '{$prefixTag}' will be added to <tagname> parameters.
EOT
        );
    }


}