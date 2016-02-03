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
use NMR\Workflow\AbstractWorkflow;
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
abstract class Command extends BaseCommand
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
        parent::initialize($input, $output);

        $this->initLogger($input, $output);
        $this->initGit($input);
        $this->initShell($input);
        $this->initClient();

        if ($this->needGitRepository() && !$this->git->isInGitRepo()) {
            $this->getLogger()->error('This command must be executed in a git repository. Try the command "twgit init" to start a git repository');
            $this->getApplication()->showUsage($this->logger);
            exit(1);
        }

        // Need to be in a git repository to initialize configuration
        $this->initConfig();

        if ($this->needTwgitRepository() && !$this->isTwgitInitialized()) {
            $this->getLogger()->error('Twgit is not initialzed. Please use "twgit init" command.');
            $this->getApplication()->showUsage($this->logger);
            exit(1);
        }

        // Twgit need to be configured before importing configuration files
        $this->importConfig();
    }

    /**
     * {inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $workflow = $this->initWorkflow();

        try {
            $action = $this->getAction($input);
            if (!method_exists($workflow, $action)) {
                $this->showUsage();
                exit(1);
            }

            call_user_func_array([$workflow, $action], [$input]);
        } catch (Exception $ex) {

            if ($ex instanceOf ConfigurationException) {
                $this->showUsage();
            } elseif ($ex instanceOf WorkflowException && $ex->getGitExitCommand()) {
                $response = $this->getGit()->execCommand($ex->getGitExitCommand());
                $this->getLogger()->writeln($response->getOutput());
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
        $action = $input->getArgument('action');

        if (empty($action) || 'help' === $action) {
            $this->showUsage();
            exit(1);
        }

        return preg_replace_callback('/-(.?)/', function($matches) {
            return ucfirst($matches[1]);
        }, sprintf('%sAction', strtolower($action)));
    }

    /**
     * @param Config $config
     * @param Git    $git
     * @param Shell  $shell
     * @param Logger $logger
     *
     * @throws ConfigurationException
     */
    protected function initWorkflow()
    {
        $classname = sprintf(
            'NMR\Workflow\%sWorkflow',
            str_replace('Command', '', TextUtil::getNamespaceShortName($this))
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
    public function initLogger(InputInterface $input, OutputInterface $output)
    {
        $this->logger = new Logger($input, $output);
    }

    /**
     * Init configuration object
     */
    protected function initConfig()
    {
        $this->config = Config::create(getenv('HOME'), $this->git->getProjectRootDir());
        $this->config->set('twgit.protected.revision', Application::REVISION);
    }

    /**
     * Import configuration files
     * @throws ConfigurationException
     */
    protected function importConfig()
    {
        foreach (['global', 'project'] as $part) {
            $configDir = sprintf($this->config->get(sprintf('twgit.protected.%s.config_dir', $part)));
            $configFile = sprintf('%s/%s', $configDir, $this->config->get('twgit.protected.config_file'));
            $this->config->import($configFile);
        }
    }

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
     * @throws WorkflowException
     */
    protected function isTwgitInitialized()
    {
        $configDir = sprintf($this->config->get('twgit.protected.project.config_dir'));
        $configFile = sprintf('%s/%s', $configDir, $this->config->get('twgit.protected.config_file'));
        return file_exists($configFile);
    }
}