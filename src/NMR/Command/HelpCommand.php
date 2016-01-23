<?php

namespace NMR\Command;

use Exception;
use NMR\Config\Config;
use NMR\Config\Loader\ConfigLoader;
use NMR\Connector\ConnectorFactory;
use NMR\Exception\ConfigurationException;
use NMR\Exception\WorkflowException;
use NMR\Log\Logger;
use NMR\Shell\Git\Git;
use NMR\Shell\Shell;
use NMR\Workflow\AbstractWorkflow;
use NMR\Workflow\Feature;
use NMR\Workflow\WorkflowFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Class TwGitApplication
 */
class TwGitApplication extends Command
{
    const
        HELP = "help"
    ;

    /** @var Config */
    private $config;

    /**
     * {inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('twgit')
            ->setDescription('PHP application for twgit workflow.')
            ->addArgument('cmd', InputArgument::OPTIONAL, 'Command', self::HELP)
            ->addArgument('action', InputArgument::OPTIONAL, 'Action')
            ->addArgument('name', InputArgument::OPTIONAL, 'Name')
            ->addOption('delete', 'd', InputOption::VALUE_NONE)
            ->addOption('minor', 'm', InputOption::VALUE_NONE)
            ->addOption('major', 'M', InputOption::VALUE_NONE)
            ->addOption('silent', 's', InputOption::VALUE_NONE, 'Disable interactive mode')
        ;
    }

    /**
     * {inheritdoc}
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $command = $input->getArgument('cmd');

        if (empty($command) || self::HELP === $command) {
            $this->showUsage($output);
            exit(0);
        }

        $action = $input->getArgument('action');

        if (empty($action) || self::HELP === $command) {
            $this->showUsage($output);
            exit(0);
        }
    }

    /**
     * @param string $key
     *
     * @return mixed|null
     * @throws ConfigurationException
     */
    protected function getConfigValue($key)
    {
        $this->getConfig();

        if (null !== $data = $this->config->get($key)) {
            return $data;
        }

        return '';
    }

    /**
     * @param $type
     *
     * @return Config
     */
    protected function getConfig()
    {
        if (is_null($this->config)) {
            $this->config = new Config([]);

            foreach (['global', 'type'] as $type) {
                if ('global' === $type) {
                    $file = realpath(__DIR__ . '/../../../app/config/config.yml');
                } else {
                    $file = realpath(getcwd()) . '/.twgit' . '/config.yml';
                }

                if (file_exists($file)) {
                    $yaml = Yaml::parse(file_get_contents($file));
                    $this->config->merge(new Config((new ConfigLoader())->convert(
                        $yaml['parameters'], '.', 'twgit'
                    )));
                }
            }
        }

        return $this->config;
    }

    /**
     * @param $action
     *
     * @return mixed
     */
    protected function actionToWorkflowMethod($action)
    {
        return preg_replace_callback('/-(.?)/', function($matches) {
            return ucfirst($matches[1]);
        }, $action) . 'Command';
    }

    /**
     * {inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $command = $input->getArgument('cmd');

        if ('install' === $command) {
            $workDir = realpath(getcwd()) . '/.twgit';
            if (!file_exists($workDir)) {
                mkdir($workDir);
            }
        }

        $action = $this->actionToWorkflowMethod($input->getArgument('action'));
        $logger = new Logger($input, $output, new QuestionHelper(), new Table($output));
        $git = (new Git($input->getOption('verbose')))->setLogger($logger);

        try {

            $workflow = $this->buildWorkflow($command);

            if (!method_exists($workflow, $action)) {
                throw new ConfigurationException(sprintf(
                    'Workflow "%s" does not action "%s".', $command, $action
                ));
            }

            $workflow
                ->setLogger($logger)
                ->setGit($git)
                ->setShell((new Shell($input->getOption('verbose')))->setLogger($logger));

            $this->addConnector($workflow);

            call_user_func_array([$workflow, $action], [$input]);

        } catch (Exception $ex) {
            $logger->error($ex->getMessage());

            if ($ex instanceOf WorkflowException && $ex->getGitExitCommand()) {
                $response = $git->runCommand($ex->getGitExitCommand());
                $logger->writeln($response->getOutput());
            }

            exit(1);
        }

        exit(0);
    }

    /**
     * @param OutputInterface $output
     */
    protected function showUsage(OutputInterface $output)
    {
        $output->writeln("<fg=cyan>(i) Usage:</>");
        $output->writeln(sprintf("<fg=white;options=bold>    %s <command> [<action>]</>", $this->getConfigValue('twgit.command')));
//        $output->writeln("        Always provide branch names without any prefix:");
//        $output->writeln(
//            "          - '%s'",
//            implode($this->pre)
//
//        );
        $output->writeln("");
        $output->writeln("<fg=cyan>(i) Available commands are:</>");
    }

    /**
     * @param $command
     *
     * @return \NMR\Workflow\AbstractWorkflowCommand
     * @throws ConfigurationException
     */
    protected function buildWorkflow($command)
    {
        return (new WorkflowFactory())->create($command, $this->getConfig());
    }

    /**
     * @param AbstractWorkflow $workflow
     *
     * @throws ConfigurationException
     */
    protected function addConnector(AbstractWorkflow $workflow)
    {
        $type = $this->getConfigValue('twgit.connectors.enabled');

        if (!empty($type)) {
            $connectorFactory = new ConnectorFactory();
            $connector = $connectorFactory->create($type, $this->getConfig());
            $workflow->setConnector($connector);
        }
    }
}