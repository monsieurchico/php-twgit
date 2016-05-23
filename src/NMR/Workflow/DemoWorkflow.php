<?php

namespace NMR\Workflow;

use NMR\Exception\ShellException;
use NMR\Exception\WorkflowException;
use Symfony\Component\Console\Input\InputInterface;
use NMR\Shell\Shell;

/**
 * Class FeatureWorkflow
 */
class DemoWorkflow extends AbstractWorkflow
{
    /**
     * @param InputInterface $input
     */
    public function startAction(InputInterface $input)
    {
        $this->startSimpleBranch(
            'demo',
            $input->getArgument('demoname'),
            $input->getOption('delete')
        );
    }
}