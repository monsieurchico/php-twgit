<?php

namespace NMR;

use Exception;
use GuzzleHttp\Client;
use NMR\Command;
use NMR\Config\Config;
use NMR\Config\ConfigAwareTrait;
use NMR\Log\Logger;
use NMR\Log\LoggerAwareTrait;
use NMR\Shell\Git\GitAwareTrait;
use NMR\Shell\ShellAwareTrait;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Command\Command as BaseCommand;
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

    /** @var Command */
    protected $command;

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
    public function doRun(InputInterface $input, OutputInterface $output)
    {
        $this->initLogger($input, $output);
        $this->initConfig();

        if (!$this->isInGitRepo()) {
            $this->showUsage();
            exit(1);
        }

        $this->initCommand($input->getFirstArgument());

        $this->command
            ->setLogger($this->logger)
            ->setConfig($this->config)
            ->setClient(new Client())
        ;

        return parent::doRun($input, $output);
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function initLogger(InputInterface $input, OutputInterface $output)
    {
        $this->logger = new Logger($input, $output);
    }

    /**
     * Initializes all the composer commands
     */
    protected function getDefaultCommands()
    {
        $commands = parent::getDefaultCommands();

        $commands = array_merge($commands, [
            'release' => new Command\ReleaseCommand(),
            'feature' => new Command\FeatureCommand(),
        ]);

        if ('phar:' === substr(__FILE__, 0, 5) || 1 === 1) {
            $commands['self-update'] = new Command\SelfUpdateCommand();
        }
        return $commands;
    }

    /**
     * @return bool
     */
    protected function isInGitRepo()
    {
        return is_dir(realpath(getcwd()) . '/.git/');
    }

    /**
     */
    protected function initConfig()
    {
        $createConfigFile = false;

        $this->config = Config::create(getenv('HOME'), realpath(getcwd()));
        $this->config->set('twgit.protected.revision', self::REVISION);

        $sourceConfig = __DIR__ . '/../../app/config/config.yml.dist';
        foreach (['global', 'project'] as $part) {

            $configDir = sprintf($this->config->get(sprintf('twgit.protected.%s.root_dir', $part)));
            $configFile = sprintf('%s/%s', $configDir, $this->config->get('twgit.protected.config_file'));

            if ('project' === $part && !$this->isInGitRepo()) {
                continue;
            }

            if (!is_dir($configDir)) {
                mkdir($configDir, 0755);
            }

            if (!is_file($configFile)) {
                copy($sourceConfig, $configFile);
                $createConfigFile = true;
                $this->logger->help(sprintf(
                    'A %s config file has been created in "%s". Please configure it !',
                    $part,
                    $configFile
                ));
            }

            $this->config->import($configFile);
            $sourceConfig = $configFile;
        }

        if ($createConfigFile) {
            exit(1);
        }
    }

    /**
     * @param string $command
     *
     * @return Command
     */
    protected function initCommand($command)
    {
        try {
            $this->command = $this->get($command);
        } catch (\Exception $ex) {
            $this->showUsage();
            exit(1);
        }
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
    protected function showUsage()
    {
        $version = self::REVISION;

        $this->logger->log('info', <<<EOT
<cb>(i)</> <c>Usage:</>
<wb>    twgit <command> [<action>]</>
    Always provide branch names wthout any prefix (see config file).

<cb>(i)</> <c>Availabe commands are:</>
    <wb>release</>         Manage your release branches.
    <wb>feature</>         Manage your feature branches.
    <wb>self-update</>     Update the version of twgit.

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