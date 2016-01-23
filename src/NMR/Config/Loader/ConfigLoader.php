<?php

namespace NMR\Config\Loader;

/**
 * Class ConfigLoader
 */
class ConfigLoader
{
    /**
     * @param array  $config
     * @param string $separator
     * @param string $prefix
     *
     * @return array
     */
    public function convert(array $config, $separator = '.', $prefix = '')
    {
        $config = $this->convertArrayToDotNotation($config, $separator, $prefix, []);
        $this->removeUselessLevels($config);

        return $config;
    }

    /**
     * @param array $config
     */
    protected function removeUselessLevels(array & $config)
    {
        foreach ($config as $key => $values) {
            if (is_array($values)) {
                unset($config[$key]);
            }
        }
    }

    /**
     * @param array  $config
     * @param string $separator
     * @param string $prefix
     * @param array $flattenParameters
     *
     * @return array
     */
    protected function convertArrayToDotNotation(array $config, $separator, $prefix, array $flattenParameters = [])
    {
        if (is_array($config)) {
            foreach ($config as $name => $subConfig) {
                $key = $prefix . $separator . $name;

                if (is_array($subConfig)) {
                    $flattenParameters = $this->convertArrayToDotNotation($subConfig, $separator, $key, $flattenParameters);
                }

                $flattenParameters[$key] = $subConfig;
            }
        }

        return $flattenParameters;
    }

}