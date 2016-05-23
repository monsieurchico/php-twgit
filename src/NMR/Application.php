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

    const
        REVISION = 'twgit_revision',
        DEFAULT_COMMAND = 'help'
    ;

    /**
     * {inheritdoc}
     */
    public function __construct()
    {
//        register_shutdown_function(function() {
//            var_dump(error_get_last());
//        });

        parent::__construct('Twgit', self::REVISION);

        $this->setDefaultCommand(self::DEFAULT_COMMAND);
    }

    /**
     * {inheritdoc}
     */
    public function doRun(InputInterface $input = null, OutputInterface $output = null)
    {
        try {
            return parent::doRun($input, $output);
        } catch (\Exception $exc) {
            $name = $this->getCommandName($input);
            $relatedCommand = null;
            if ($this->has($name)) {
                $relatedCommand = $this->get($name);
            }

            /** @var NMRCommand\HelpCommand $command */
            $command = $this->get(self::DEFAULT_COMMAND);
            $command
                ->setRelatedCommand($relatedCommand)
                ->setErrorMessage($exc->getMessage());

            $exitCode = $this->doRunCommand($command, $input, $output);

            return $exitCode;
        }
    }

    /**
     * Initializes all the composer commands
     */
    protected function getDefaultCommands()
    {
        return [
            'release'       => new Command\ReleaseCommand(),
            'hotfix'        => new Command\HotfixCommand(),
            'feature'       => new Command\FeatureCommand(),
            'init'          => new Command\InitCommand(),
            'self-update'   => new Command\SelfUpdateCommand(),
            'help'          => new Command\HelpCommand(),
            'demo'          => new Command\DemoCommand()
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
}
