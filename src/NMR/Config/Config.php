<?php

namespace NMR\Config;

use NMR\Config\Loader\ConfigLoader;
use NMR\Exception\ConfigurationException;
use Symfony\Component\Yaml\Yaml;

/**
 * Class Config
 */
class Config
{
    /** @var array */
    private $storage;

    /**
     * @param array $config
     */
    public function __construct(array $config = array())
    {
        $this->storage = $config;
    }

    /**
     * @param string $globalRootDir
     * @param string $projectRootDir
     *
     * @return Config
     */
    public static function create($globalRootDir, $projectRootDir)
    {
        $configDir = '.twgit';

        return new self([
            'twgit.protected.global.root_dir' => $globalRootDir,
            'twgit.protected.global.config_dir' => $globalRootDir . '/' . $configDir,
            'twgit.protected.project.root_dir' => $projectRootDir,
            'twgit.protected.project.config_dir' => $projectRootDir . '/' . $configDir,
            'twgit.protected.config_file' => 'config.yml',
            'twgit.protected.global.versions_dir' => sprintf('%s/versions', $globalRootDir),
            'twgit.protected.global.cache_dir' => sprintf('%s/cache', $projectRootDir),
        ]);
    }

    /**
     * @return array
     */
    public function getStorage()
    {
        return $this->storage;
    }

    /**
     * @param string $file
     *
     * @return $this
     * @throws ConfigurationException
     */
    public function import($file)
    {
        if (!file_exists($file)) {
            throw new ConfigurationException(sprintf('Configuration file "%s" does not exist.', $file));
        }

        $yaml = Yaml::parse(file_get_contents($file));
        if (is_array($yaml) && isset($yaml['parameters'])) {
            $this->merge(new Config((new ConfigLoader())->convert($yaml['parameters'], '.', 'twgit')));
        }

        return $this;
    }

    /**
     * @param Config $config
     *
     * @return $this
     */
    public function merge(Config $config)
    {
        return $this->override($config->getStorage());
    }

    /**
     * @param array $data
     *
     * @return $this
     * @throws ConfigurationException
     */
    public function override(array $data)
    {
        foreach ($data as $key => $datum) {
            $this->set($key, $datum);
        }

        return $this;
    }

    /**
     * @param $key
     *
     * @return mixed|null
     */
    public function has($key)
    {
        return array_key_exists($key, $this->storage);
    }

    /**
     * @param string $key
     * @param string $default
     *
     * @return mixed|null
     */
    public function get($key, $default = null)
    {
        return $this->has($key) ? $this->storage[$key] : $default;
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return $this
     * @throws ConfigurationException
     */
    public function set($key, $value)
    {
        if ($this->has($key) && 0 === strpos($key, 'twgit.protected')) {
            throw new ConfigurationException('You cannot override protected configuration.');
        }

        $this->storage[$key] = $value;

        return $this;
    }
}