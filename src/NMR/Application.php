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
    use LoggerAwareTrait;

    const
        REVISION = 'twgit_revision',
        DEFAULT_COMMAND = 'help'
    ;

    /**
     * {inheritdoc}
     */
    public function __construct()
    {
        parent::__construct('Twgit', self::REVISION);

        $this->setDefaultCommand(self::DEFAULT_COMMAND);
    }

    /**
     * {inheritdoc}
     */
    public function doRun(InputInterface $input = null, OutputInterface $output = null)
    {
        try {
            $this->setLogger(new Logger($input, $output));

            return parent::doRun($input, $output);
        } catch (\Exception $exc) {
            $name = $this->getCommandName($input);

            if ($exc->getMessage()) {
                $this->getLogger()->error('ERROR : ' . $exc->getMessage());
                $this->getLogger()->help('Run <b>twgit ' . $name . ' -h</b> to display the help.');
            }

            return 0;
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
            'help' => new Command\HelpCommand(),
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