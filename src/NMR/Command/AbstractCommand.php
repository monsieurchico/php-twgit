<?php

namespace NMR\Command;

use Exception;
use GuzzleHttp\Client;
use NMR\Application;
use NMR\Client\GuzzleHttpClientAwareTrait;
use NMR\Config\Config;
use NMR\Config\ConfigAwareTrait;
use NMR\Connector\ConnectorFactory;
use NMR\Exception\ConfigurationException;
use NMR\Exception\WorkflowException;
use NMR\Log\Logger;
use NMR\Log\LoggerAwareTrait;
use NMR\Shell\Git\Git;
use NMR\Shell\Git\GitAwareTrait;
use NMR\Shell\Shell;
use NMR\Shell\ShellAwareTrait;
use NMR\Util\TextUtil;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Base class for twgit commands
 *
 * @author Romain Derocle <rderocle@gmail.com>
 */
abstract class AbstractCommand extends BaseCommand
{
    use
        LoggerAwareTrait,
        ShellAwareTrait,
        GitAwareTrait,
        ConfigAwareTrait,
        GuzzleHttpClientAwareTrait
    ;

    /** @var array */
    protected $actions;

    /**
     */
    abstract public function showUsage();

    /**
     * @return bool
     */
    abstract public function needGitRepository();

    abstract public function needTwgitRepository();

    /**
     * {inheritdoc}
     */
    protected function configure()
    {
        $this
            ->addArgument('command', InputArgument::REQUIRED, 'Command')
            ->addArgument('action', InputArgument::OPTIONAL, 'Action')
            ->addOption('silent', 's', InputOption::VALUE_NONE, 'Disable interactive mode')
        ;
    }

    /**
     * {inheritdoc}
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->initLogger($input, $output);
        $this->initGit($input);
        $this->initShell($input);
        $this->initClient();

        if ($this->needGitRepository() && !$this->git->isInGitRepo()) {
            $this->getLogger()->error('This command must be executed in a git repository. Try the command "twgit init" to start a git repository');
            exit(1);
        }

        // Need to be in a git repository to initialize configuration
        $this->initConfig();

        if ($this->needTwgitRepository() && !$this->isTwgitInitialized()) {
            $this->getLogger()->error('Twgit is not initialzed. Please use "twgit init" command.');
            exit(1);
        }
    }

    /**
     * @return array
     */
    protected function checkAutoUpdate()
    {
        $command = null;
        $action = null;

        if ($this->getConfig()->get('twgit.update.auto_check')) {

            $checkUpdatePeriod = (int)$this->getConfig()->get('twgit.update.nb_days');

            $lastUpdateFile = sprintf(
                '%s%s%s',
                $this->getConfig()->get('twgit.protected.global.config_dir'),
                DIRECTORY_SEPARATOR,
                $this->getConfig()->get('twgit.update.log_filename')
            );

            if (file_exists($lastUpdateFile)) {
                $content = file_get_contents($lastUpdateFile);
                if (!empty($content)) {
                    $lastUpdate = file_get_contents($lastUpdateFile);
                }
            }

            if (empty($lastUpdate)) {
                $lastUpdate = sprintf('now - %d days', $checkUpdatePeriod + 1);
            }

            $lastUpdate = new \DateTime($lastUpdate);

            $days = (new \DateTime('now'))->diff($lastUpdate)->format('%a');

            if ($days >  $checkUpdatePeriod) {

                $this->getLogger()->info('Your application has not been updated since ' . $lastUpdate->format('Y-m-d H:i:s'));

                $command = 'SelfUpdateCommand';
                $action = 'defaultAction';
            }
        }

        return [$command, $action];
    }

    /**
     * {inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        list($command, $action) = $this->checkAutoUpdate();

        $workflow = $this->initWorkflow($command);

        try {
            if (!$action) {
                $action = $this->getAction($input);
            }

            if (!method_exists($workflow, $action)) {
                $this->showUsage();
                exit(1);
            }

            call_user_func_array([$workflow, $action], [$input]);
        } catch (Exception $ex) {

            if ($ex instanceOf ConfigurationException) {
                $this->showUsage();
            } elseif ($ex instanceOf WorkflowException) {

                $this->getLogger()->error($ex->getMessage());

                if ($ex->getGitExitCommand()) {
                    $response = $this->getGit()->execCommand($ex->getGitExitCommand());
                    $this->getLogger()->writeln($response->getOutput());
                }

            } else {
                throw $ex;
            }

            exit(1);
        }

        exit(0);
    }

    // ---

    /**
     * @param InputInterface $input
     *
     * @return string
     */
    protected function getAction(InputInterface $input)
    {
        if ($input->hasArgument('action')) {
            $action = $input->getArgument('action');
        } else {
            $action = 'default';
        }

        return preg_replace_callback('/-(.?)/', function($matches) {
            return ucfirst($matches[1]);
        }, sprintf('%sAction', strtolower($action)));
    }

    /**
     * @param null $overrideCommand
     * @return mixed
     */
    protected function initWorkflow($overrideCommand = null)
    {
        $classname = sprintf(
            'NMR\Workflow\%sWorkflow',
            str_replace('Command', '', TextUtil::getNamespaceShortName($overrideCommand ?: $this))
        );

        $workflow = (new $classname($this->config))
            ->setGit($this->git)
            ->setShell($this->shell)
            ->setLogger($this->logger);

        $type = $this->config->get('twgit.connectors.enabled');

        if (!empty($type)) {
            $connectorFactory = new ConnectorFactory();
            $connector = $connectorFactory->create($type, $this->getConfig(), $this->getClient());
            $workflow->setConnector($connector);
        }

        return $workflow;
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
     * Init configuration object
     */
    protected function initConfig()
    {
        if ($this->git->isInGitRepo()) {
            $this->config = Config::create(getenv('HOME'), $this->git->getProjectRootDir());
        } else {
            $this->config = Config::create(getenv('HOME'));
        }

        $this->config->set('twgit.protected.revision', Application::REVISION);

        foreach (['global', 'project'] as $part) {
            if ('project' === $part && !$this->git->isInGitRepo()) {
                continue;
            }

            $this->initTwgitConfDir($part);
        }
    }

    /**
     * Initialize twit config directory
     * @param $part
     * @throws ConfigurationException
     */
    protected function initTwgitConfDir($part)
    {
        $sourceConfig = __DIR__ . '/../../../app/config/config.yml.dist';
        $configDir = sprintf($this->config->get(sprintf('twgit.protected.%s.config_dir', $part)));
        $configFile = sprintf('%s/%s', $configDir, $this->config->get('twgit.protected.config_file'));

        if (!file_exists($configDir)) {
            // Create new config directory
            mkdir($configDir, 0755);
            copy($sourceConfig, $configFile);
            $this->logger->help(sprintf(
                'A %s config file has been created in "%s". Please configure it !',
                $part,
                $configFile
            ));
        } elseif (is_file($configDir)) {
            // Manage old .twgit file
            rename($configDir, $configDir . '_old');
            mkdir($configDir, 0755);
            rename($configDir . '_old', $configDir . DIRECTORY_SEPARATOR . '.twgit_old');
            copy($sourceConfig, $configFile);

            $this->logger->help(sprintf(
                'A %s config file has been created in "%s". Please configure it using your old configuration in "%s" !',
                $part,
                $configFile,
                $configDir . DIRECTORY_SEPARATOR . '.twgit_old'
            ));
        }

        $this->config->import($configFile);
    }

    /**
     *
     */
    protected function initClient()
    {
        $this->client = new Client();
    }

    /**
     * @param InputInterface $input
     */
    protected function initGit(InputInterface $input)
    {
        $this->git = (new Git($input->getOption('verbose')))->setLogger($this->logger);
    }

    /**
     * @param InputInterface $input
     */
    protected function initShell(InputInterface $input)
    {
        $this->shell = (new Shell($input->getOption('verbose')))->setLogger($this->logger);
    }

    /**
     * Check if twgit is initialized by checking if config exists
     *
     * @throws WorkflowException
     *
     * @return bool
     */
    protected function isTwgitInitialized()
    {
        $configDir = sprintf($this->config->get('twgit.protected.project.config_dir'));
        $configFile = sprintf('%s/%s', $configDir, $this->config->get('twgit.protected.config_file'));

        return file_exists($configFile);
    }
}