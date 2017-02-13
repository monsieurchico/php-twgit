<?php

namespace NMR\Command;

use Symfony\Component\Console\Input\InputInterface;

/**
 * Class SelfUpdateCommand
 */
class SelfUpdateCommand extends AbstractCommand
{
    /**
     * {inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('self-update')
            ->setAliases(['selfupdate'])
            ->setDescription('Update your twgit version.')
//            ->addOption('rollback', 'r', InputOption::VALUE_NONE, 'Rollback to previous version.')
//            ->addOption('clean-old-versions', 'c', InputOption::VALUE_NONE, 'Clean old downloaded verions.')
        ;
    }

    /**
     * @param InputInterface $input
     * @return string
     */
    protected function getAction(InputInterface $input)
    {
        return 'defaultAction';
    }

    /**
     * {inheritdoc}
     */
    public function needGitRepository()
    {
        return false;
    }

    /**
     * {inheritdoc}
     */
    public function needTwgitRepository()
    {
        return false;
    }

    /**
     * {inheritdoc}
     */
    public function showUsage()
    {
        $this->getLogger()->writeln(<<<EOT
<cb>(i)</> <c>Usage</>
<wb>    twgit self-update</>
EOT
        );
    }


}