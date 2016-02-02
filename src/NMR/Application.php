<?php

namespace NMR;

use GuzzleHttp\Client;
use NMR\Command as NMRCommand;
use NMR\Config\ConfigAwareTrait;
use NMR\Log\Logger;
use NMR\Log\LoggerAwareTrait;
use NMR\Shell\Git\GitAwareTrait;
use NMR\Shell\ShellAwareTrait;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Application
 *
 * @author Romain Derocle <rderocle@gmail.com>
 */
class Application extends BaseApplication
{
    use
        LoggerAwareTrait,
        ConfigAwareTrait,
        GitAwareTrait,
        ShellAwareTrait
        ;

    const REVISION = 'twgit_revision';

    /**
     * {inheritdoc}
     */
    public function __construct()
    {
//        register_shutdown_function(function() {
//            var_dump(error_get_last());
//        });

        parent::__construct('Twgit', self::REVISION);
    }

    /**
     * {inheritdoc}
     */
    public function doRun(InputInterface $input = null, OutputInterface $output = null)
    {
        try {
            return parent::doRun($input, $output);
        } catch (\Exception $exc) {
            $logger = new Logger($input, $output);
            $logger->error($exc->getMessage());

            $name = $this->getCommandName($input);
            if ($this->has($name)) {
                $command = $this->get($name);
                $command->showUsage();
            } else {
                $this->showUsage($logger);
            }
        }
    }

    /**
     * Initializes all the composer commands
     */
    protected function getDefaultCommands()
    {
        return [
            'release' => new Command\ReleaseCommand(),
            'hotfix' => new Command\HotfixCommand(),
            'feature' => new Command\FeatureCommand(),
            'init' => new Command\InitCommand(),
            'self-update' => new Command\SelfUpdateCommand(),
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getDefinition()
    {
        $inputDefinition = parent::getDefinition();
        $inputDefinition->setArguments();

        return $inputDefinition;
    }

    /**
     * {inheritdoc}
     */
    public function showUsage($logger)
    {
        $version = self::REVISION;

        $logger->writeln(<<<EOT
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