<?php

namespace NMR\Log;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * Class Logger
 */
class Logger
{
    /** @var InputInterface */
    protected $input;

    /** @var OutputInterface */
    protected $output;

    /** @var QuestionHelper */
    protected $questionHelper;

    /** @var Table */
    protected $tableHelper;

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @param QuestionHelper  $questionHelper
     * @param Table           $table
     */
    public function __construct(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        $this->init();
    }

    /**
     * @return QuestionHelper
     */
    public function getQuestionHelper()
    {
        if (is_null($this->questionHelper)) {
            $this->questionHelper = new QuestionHelper();
        }

        return $this->questionHelper;
    }

    /**
     * @return Table
     */
    public function getTableHelper()
    {
        if (is_null($this->tableHelper)) {
            $this->tableHelper = new Table($this->output);
        }

        return $this->tableHelper;
    }

    /**
     *
     */
    protected function init()
    {
        $styles = [
            'error' => ['fg' => 'red'],
            'error_bold' => ['fg' => 'red', 'options' => ['bold']],
            'error_header' => ['fg' => 'yellow', ['options' => ['underline']]],
            'feature_subject' => ['fg' => 'cyan'],
            'help' => ['fg' => 'cyan'],
            'help_detail' => ['fg' => 'cyan', 'options' => ['bold']],
            'info' => ['fg' => 'white', ['options' => ['bold']]],
            'code' => ['fg' => 'magenta'],
            'processing' => ['fg' => 'default'],
            'b' => ['options' => ['bold']],
            'u' => ['options' => ['underscore']],
            'warning' => ['fg' => 'yellow'],
            'warning_bold' => ['fg' => 'yellow', ['options' => ['bold']]],

            'c' => ['fg' => 'cyan'],
            'cb' => ['fg' => 'cyan', ['options' => ['bold']]],
            'r' => ['fg' => 'red'],
            'rb' => ['fg' => 'red', ['options' => ['bold']]],
            'y' => ['fg' => 'yellow'],
            'yb' => ['fg' => 'yellow', ['options' => ['bold']]],
            'b' => ['fg' => 'blue'],
            'bb' => ['fg' => 'blue', ['options' => ['bold']]],
            'w' => ['fg' => 'white'],
            'wb' => ['fg' => 'white', ['options' => ['bold']]],
            'm' => ['fg' => 'magenta'],
            'mb' => ['fg' => 'magenta', ['options' => ['bold']]],
            'g' => ['fg' => 'green'],
            'gb' => ['fg' => 'green', ['options' => ['bold']]],
        ];

        foreach ($styles as $name => $definition) {
            $definition = array_merge([
                'fg' => 'default',
                'bg' => 'default',
                'options' => []
            ], $definition);

            $this->output->getFormatter()->setStyle(
                $name,
                new OutputFormatterStyle($definition['fg'], $definition['bg'], $definition['options'])
            );
        }
    }

    /**
     * @param string $message
     */
    public function writeln($message)
    {
        $this->output->writeln($message);
    }

    /**
     * @param $message
     *
     * @return string
     */
    public function table(array $data, array $headers)
    {
        $this->getTableHelper()
            ->setHeaders($headers)
            ->setRows($data);

        $this->table->render();
    }

    /**
     * @param $message
     *
     * @return string
     */
    public function ask($message, $newLine = true)
    {
        return $this->getQuestionHelper()->ask($this->input, $this->output, new ConfirmationQuestion(sprintf('%s [Y/N] ', $message)));
    }

    /**
     * @param $message
     */
    public function help($message, $newLine = true)
    {
        $this->log('help', '(i) ' . $message, $newLine);
    }

    /**
     * @param $message
     */
    public function processing($message, $newLine = true)
    {
        $this->log('processing', $message, $newLine);
    }

    /**
     * @param string $message
     * @param array  $newLine
     */
    public function emergency($message, $newLine = true)
    {
        $this->log('emergency', $message, $newLine);
    }

    /**
     * @param string $message
     * @param array  $newLine
     */
    public function alert($message, $newLine = true)
    {
        $this->log('alert', $message, $newLine);
    }

    /**
     * @param string $message
     * @param array  $newLine
     */
    public function critical($message, $newLine = true)
    {
        $this->log('critical', $message, $newLine);
    }

    /**
     * @param string $message
     * @param array  $newLine
     */
    public function error($message, $newLine = true)
    {
        $this->log('error', $message, $newLine);
    }

    /**
     * @param string $message
     * @param array  $newLine
     */
    public function warning($message, $newLine = true)
    {
        $this->log('warning', $message, $newLine);
    }

    /**
     * @param string $message
     * @param array  $newLine
     */
    public function notice($message, $newLine = true)
    {
        $this->log('notice', $message, $newLine);
    }

    /**
     * @param string $message
     * @param array  $newLine
     */
    public function info($message, $newLine = true)
    {
        $this->log('info', $message, $newLine);
    }

    /**
     * @param string $message
     * @param array  $newLine
     */
    public function debug($message, $newLine = true)
    {
        $this->log('debug', $message, $newLine);
    }

    /**
     * @param mixed  $level
     * @param string $message
     * @param array  $newLine
     */
    public function log($level, $message, $newLine = true)
    {
        if ($newLine) {
            $this->output->writeln(sprintf('<%s>%s</>', $level, $message));
        } else {
            $this->output->write(sprintf('<%s>%s</>', $level, $message));
        }
    }
}