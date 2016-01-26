<?php

namespace NMR\Command;

use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class HotfixCommand
 */
class HotfixCommand extends Command
{
    /**
     * {inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('hotfix')
            ->setDescription('Manage your hotfix branches.')
            ->addArgument('name', InputArgument::OPTIONAL, 'Name')
            ->addOption('no-fetch', 'F', InputOption::VALUE_NONE)
        ;
    }

    /**
     * {inheritdoc}
     */
    protected function showUsage()
    {
        $prefixHotfix = $this->getConfig()->get('twgit.workflow.prefixes.hotfix');
        $prefixTag = $this->getConfig()->get('twgit.workflow.prefixes.tag');
        $stable = $this->getConfig()->get('twgit.git.stable');

        $this->getLogger()->help(<<<EOT
<cb>(i)</> <c>Usage</>
<wb>    twgit hotfix [<action>]</>

<cb>(i)</> <c>Availabe commands are:</>
    <wb>finish [-s]</>
            Merge current hotfix branch into "{$stable}", create a new tag and push.
            Add [is] to run in non-interactive mode (always say yes.)

    <wb>push</>
            Push current hotfix to "origin" repository.
            It's a shortcut for : "git push origin hotfix..."

    <wb>remove <hotfixname></>
            Remove both local and remote specified hotfix branch.
            Despite that, create the same tag as finish action to clearly distinguish the next hotfix from this one.
            Prefix '{$prefixHotfix}' will be added to <hotfixname> parameters.

    <wb>start [-s]</>
            Create both a new local and remote hotfix, or fetch the remote hotfix, or checkout the local hotfix.
            <hotfixname> must use major.minor.revision format.
            Hotfix name will be generated by incrementing revision of the last tag:
                v1.2.3 => hotfix/1.2.4
            Add -s to run in nointeractive mode (always say yes).

    Prefix '{$prefixHotfix}' will be added to <hotfixname> parameters.

EOT
        );
    }


}