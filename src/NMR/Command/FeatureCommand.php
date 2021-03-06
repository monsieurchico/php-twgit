<?php

namespace NMR\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class FeatureCommand
 */
class FeatureCommand extends AbstractCommand
{
    /**
     * {inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('feature')
            ->setDescription('Manage your feature branches.')
            ->addArgument('name', InputArgument::OPTIONAL, 'Name')
            ->addOption('delete', 'd', InputOption::VALUE_NONE, 'Delete a local branch to recreate it.')
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
        $prefixFeature = $this->getConfig()->get('twgit.workflow.prefixes.feature');

        $this->getLogger()->writeln(<<<EOT
<cb>(i)</> <c>Usage</>
<wb>    twgit feature <action></>

<cb>(i)</> <c>Availabe commands are:</>
    <wb>merge-into-release [<featurename]</>
        Try to merge specified feature into current release.
        If no <featurename> is specified, then ask to use current feature.

    <wb>start [<featurename] [-d]</>
        Create both a new local and remote feature, of tech the remote feature, or checkout the local feature.
        Add -d to delete beforehand local feature if exists.


    Prefix '{$prefixFeature}' will be added to <featurename> and <newfeaturename> parameters.
EOT
        );
    }


}