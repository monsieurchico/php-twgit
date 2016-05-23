<?php

namespace NMR\Command;

use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class DemoCommand extends Command
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('demo')
            ->setDescription('Manage your demo branches.')
            ->addArgument('demoname', InputArgument::REQUIRED, 'DemoName')
            ->addOption('delete', 'd', InputOption::VALUE_NONE, 'Delete a local branch to recreate it.')
        ;
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
        $prefixDemo = $this->getConfig()->get('twgit.workflow.prefixes.demo');
        $originDemo = $this->getConfig()->get('twgit.git.origin');

        $this->getLogger()->help('
            <cb>(i)</> <c>Usage</>
            <wb>    twgit feature [<action>]</>
            <cb>(i)</> <c>Availabe commands are:</>
            <wb>list [<demoname>] [-F]</>
                List remote demos with their merged features.
                If <demoname> is specified, then focus on this demo. Add -F to do not make fetch.

            <wb>merge-demo <demoname> </>
                Try to merge specified demo into current demo.

            <wb>merge-feature <featurename> </>
                Try to merge specified feature into current demo.

            <wb>push</>
                Push current demo to ' . $originDemo .' repository.
                It s a shortcut for: git push ' . $originDemo . ' ' . $prefixDemo . '...

            <wb>remove <demoname> </>
                Remove both local and remote specified demo branch. No feature will be removed.

            <wb>start <demoname> [-d] </>
                Create both a new local and remote demo, or fetch the remote demo or checkout the local demo.
                Add -d to delete beforehand local demo if exists.

            <wb>status [<demoname>] </>
                Display information about specified demo: long name if a connector is set, last commit,
                status between local and remote demo and execute a git status if specified demo is the current branch.
                If no <demoname> is specified, then use current demo.
    
            <wb>update-features </>
                Try to update features into current demo.
                Prefix ' . $prefixDemo . ' will be added to <demoname> parameter.

            <wb>[help] </>
                 Display this help.
           '
        );
    }

    /**
     * {inheritdoc}
     */
    public function needGitRepository()
    {
        return true;
    }
}   
