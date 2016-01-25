<?php

namespace NMR\Command;

use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class FeatureCommand
 */
class FeatureCommand extends Command
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
    protected function showUsage()
    {
        $prefixFeature = $this->getConfig()->get('twgit.workflow.prefixes.feature');

        $this->getLogger()->help(<<<EOT
<cb>(i)</> <c>Usage</>
<wb>    twgit feature [<action>]</>

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