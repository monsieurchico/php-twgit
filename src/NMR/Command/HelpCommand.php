<?php

namespace NMR\Command;

use NMR\Application;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class FeatureCommand
 */
class HelpCommand extends Command
{
    /**
     * {inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('help')
            ->addArgument('command', InputArgument::OPTIONAL, 'Command')
            ->addArgument('action', InputArgument::OPTIONAL, 'Action')
            ->addOption('silent', 's', InputOption::VALUE_NONE, 'Disable interactive mode')
        ;
    }

    /**
     * {inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        dump($input->getArgument('command'));
        die;

        $this->showUsage();
    }

    /**
     * {inheritdoc}
     */
    public function needGitRepository()
    {
        return false;
    }

    /**
     * {inheritdoc}
     */
    public function needTwgitRepository()
    {
        return false;
    }

    /**
     * {inheritdoc}
     */
    public function showUsage()
    {
        $version = Application::REVISION;

        $this->getLogger()->info(<<<EOT
<cb>(i)</> <c>Usage:</>
<wb>    twgit <command> [<action>]</>
    Always provide branch names wthout any prefix (see config file).

<cb>(i)</> <c>Availabe commands are:</>
    <wb>release</>         Manage your release branches.
    <wb>hotfix</>          Manage your hotfix branches.
    <wb>feature</>         Manage your feature branches.
    <wb>self-update</>     Update the version of twgit.

    <wb>init <tagname> [<url>]</>
                    Initialize git repository for twgit:
                      – git init if necessary
                      – add remote origin <url> if necessary
                      – create 'stable' branch if not exists, or pull 'origin/stable'
                        branch if exists
                      – create <tagname> tag on HEAD of stable, e.g. 1.2.3, using
                        major.minor.revision format.
                        Prefix 'v' will be added to the specified <tagname>.
                      A remote repository must exists.

<cb>(i) See also:</>
    Try 'twgit command [help]' for more details

<cb>(i) About:</>
    Contact:            git@github.com:monsieurchico/php-twgit.git
    Adapted from:       git@github.com:Twenga/twgit.git
    Revision:           {$version}
EOT
        );
    }

}