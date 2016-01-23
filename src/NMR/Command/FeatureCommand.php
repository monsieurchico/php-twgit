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
    <wb>start [<featurename] [-M|-m]
            Create both a new local and remote feature, or fetch the remote feature, or checkout the local feature.
            <featurename> must use major.minor.revision format.
            If no <featurename> is specified, a name will be generated buy incrementing the last tag (e.g. v1.2.3):
                -M for a new major version (-> {$prefixFeature}2.0.0)
                -m for a new minor version (-> {$prefixFeature}1.3.0)

    Prefix '{$prefixFeature}' will be added to <featurename> parameters.
    Prefix 'v' will be added to <tagname> parameters.
EOT
        );
    }


}