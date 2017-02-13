<?php

namespace NMR\Workflow;

use GuzzleHttp\Client;
use NMR\Application;
use NMR\Client\GuzzleHttpClientAwareTrait;
use NMR\Config\Config;
use NMR\Exception\ConfigurationException;
use NMR\Exception\WorkflowException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Filesystem\Filesystem;
use NMR\Shell\Git\Git;

/**
 * Class SelfUpdateWorkflow
 */
class SelfUpdateWorkflow extends AbstractWorkflow
{
    use
        GuzzleHttpClientAwareTrait
    ;

    const
        REMOTE_URL_REVISION_INFO = 'http://monsieurchico.github.io/php-twgit/deploy/REVISION.md',
        REMOTE_URL_PHAR = 'http://monsieurchico.github.io/php-twgit/deploy/twgit.phar'
    ;

    /**
     * SelfUpdateWorkflow constructor.
     */
    public function __construct(Config $config)
    {
        parent::__construct($config);
        $this->client = new Client();
    }

    /**
     * @param InputInterface $input
     *
     * @throws WorkflowException
     */
    public function defaultAction(InputInterface $input)
    {
        if ($input->hasOption('rollback')) {
            $this->rollback();
        } elseif ($input->hasOption('clean-old-versions')) {
            $this->cleanOldVersions();
        } else {
            $this->handleUpgrade();
        }
    }

    /**
     *
     */
    protected function handleUpgrade()
    {
        $currentRevision = explode('-', $this->getConfig()->get('twgit.protected.revision'))[0];

        $currentRevision = '0.15.1';

        $distantRevision = explode('-', str_replace("\n", '', file_get_contents(self::REMOTE_URL_REVISION_INFO . '?c=' . uniqid())))[0];

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

        $versionDir = sprintf('%s/versions', $this->getConfig()->get('twgit.protected.global.root_dir'));
        if (!is_dir($versionDir)) {
            mkdir($versionDir, 0755);
        }

        $newPharFile = sprintf('%s/twgit-%s.phar', $versionDir, $distantRevision);
        $oldPharFile = sprintf('%s/twgit-%s.phar-old', $versionDir, $currentRevision);
        $currentPharFile = realpath(str_replace(['phar://', '/src/NMR/Workflow'], ['', ''], __DIR__));

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

        file_put_contents(sprintf(
            '%s%s%s',
            $this->getConfig()->get('twgit.protected.global.project_dir'),
            DIRECTORY_SEPARATOR,
            $this->getConfig()->get('twgit.update.log_filename')
        ), date('Y-m-d H:i:s'));
    }
}