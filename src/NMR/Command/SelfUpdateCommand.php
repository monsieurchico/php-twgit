<?php

namespace NMR\Command;

use NMR\Shell\Git\Git;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class SelfUpdateCommand
 */
class SelfUpdateCommand extends Command
{
    const
        REMOTE_URL_REVISION_INFO = 'http://monsieurchico.github.io/php-twgit/deploy/REVISION.md',
        REMOTE_URL_PHAR = 'http://monsieurchico.github.io/php-twgit/deploy/twgit.phar'
    ;

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
            ->addOption('rollback', 'r', InputOption::VALUE_NONE, 'Rollback to previous version.')
            ->addOption('clean-old-versions', 'c', InputOption::VALUE_NONE, 'Clean old downloaded verions.')
        ;
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
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $currentRevision = explode('-', $this->getConfig()->get('twgit.protected.revision'))[0];
        $distantRevision = explode('-', str_replace("\n", '', file_get_contents(self::REMOTE_URL_REVISION_INFO . '?c=' . time())))[0];

        $this->getLogger()->help(sprintf(
            'Current revision is "%s" and remote revision "%s".',
            $currentRevision,
            $distantRevision
        ));

        $result = $this->getGit()->compareVersions($currentRevision, $distantRevision);

        switch ($result) {
            case Git::VERSION_EQUALS:
                break;
            case Git::VERSION_PREVIOUS:
                $this->upgrade($currentRevision, $distantRevision);
                break;
        }
    }

    /**
     * @param string $currentRevision
     * @param string $distantRevision
     */
    protected function upgrade($currentRevision, $distantRevision)
    {
        $fs = new Filesystem();

        $versionDir = sprintf('%s/versions', $this->config->get('twgit.protected.global.root_dir'));
        if (!is_dir($versionDir)) {
            mkdir($versionDir, 0755);
        }

        $newPharFile = sprintf('%s/twgit-%s.phar', $versionDir, $distantRevision);
        $oldPharFile = sprintf('%s/twgit-%s.phar-old', $versionDir, $currentRevision);
        $currentPharFile = realpath(str_replace(['phar://', '/src/NMR/Command'], ['', ''], __DIR__));

        $this->getLogger()->info('Download new version...');
        $this->getClient()
            ->get(self::REMOTE_URL_PHAR, [
                'save_to' => $newPharFile
            ]);

        if ($fs->exists($newPharFile)) {
            $this->getLogger()->info('Backup current version...');

            if ($fs->exists($oldPharFile)) {
                $fs->remove($oldPharFile);
            }
            $fs->rename($currentPharFile, $oldPharFile);

            $this->getLogger()->info('Install new version...');
            $fs->remove($currentPharFile);
            $fs->rename($newPharFile, $currentPharFile);
            $fs->chmod($currentPharFile, 0777);
        } else {
            $this->getLogger()->error('Failed to download new version.');
        }
    }

    /**
     * {inheritdoc}
     */
    protected function showUsage()
    {
        $prefixRelease = $this->getConfig()->get('twgit.workflow.prefixes.release');

        $this->getLogger()->help(<<<EOT
<cb>(i)</> <c>Usage</>
<wb>    twgit self-update [-r|-c]</>
EOT
        );
    }


}