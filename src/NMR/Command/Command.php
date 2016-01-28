<?php

namespace NMR\Command;

use Exception;
use NMR\Client\GuzzleHttpClientAwareTrait;
use NMR\Config\ConfigAwareTrait;
use NMR\Connector\ConnectorFactory;
use NMR\Exception\ConfigurationException;
use NMR\Exception\WorkflowException;
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

    /** @var AbstractWorkflow */
    protected $workflow;

    /**
     * @param AbstractWorkflow $workflow
     *
     * @return Command
     */
    public function setWorkflow(AbstractWorkflow $workflow)
    {
        $this->workflow = $workflow;

        return $this;
    }

    /**
     */
    abstract protected function showUsage();

    /**
     * @return bool
     */
    abstract public function needGitRepository();

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

        $this->initGit($input);
        $this->initShell($input);
    }

    /**
     * {inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initWorkflow();

        try {
            $action = $this->getAction($input);
            if (!method_exists($this->workflow, $action)) {
                $this->showUsage();
                exit(1);
            }

            call_user_func_array([$this->workflow, $action], [$input]);
        } catch (Exception $ex) {
            $this->getLogger()->error($ex->getMessage());

            if ($ex instanceOf ConfigurationException) {
                $this->showUsage();
            } elseif ($ex instanceOf WorkflowException && $ex->getGitExitCommand()) {
                $response = $this->getGit()->execCommand($ex->getGitExitCommand());
                $this->getLogger()->writeln($response->getOutput());
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

        $this->workflow = (new $classname($this->config))
            ->setGit($this->git)
            ->setShell($this->shell)
            ->setLogger($this->logger);

        $type = $this->config->get('twgit.connectors.enabled');

        if (!empty($type)) {
            $connectorFactory = new ConnectorFactory();
            $connector = $connectorFactory->create($type, $this->getConfig(), $this->getClient());
            $this->workflow->setConnector($connector);
        }
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
}