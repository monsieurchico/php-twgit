<?php

namespace NMR\Connector;

use GuzzleHttp\Client;
use NMR\Config\Config;
use NMR\Exception\ConfigurationException;
use ReflectionClass;
use ReflectionParameter;
use NMR\Util\TextUtil;

/**
 * Class ConnectorFactory
 */
class ConnectorFactory
{
    /**
     * @param string $type
     * @param Config $config
     * @param Client $client
     *
     * @return AbstractConnectorCommand
     * @throws ConfigurationException
     */
    public function create($type, Config $config, Client $client)
    {
        $class = sprintf('NMR\Connector\%sConnector', ucfirst($type));

        if (!class_exists($class)) {
            throw new ConfigurationException(sprintf('Invalid Connector "%s".', $type));
        }

        $reflectionClass = new ReflectionClass($class);
        $constructorParameters = $reflectionClass->getConstructor()->getParameters();
        $parameters = [];

        /** @var ReflectionParameter $constructorParameter */
        foreach ($constructorParameters as $constructorParameter) {
            $name = $constructorParameter->getName();
            $configName = sprintf('twgit.connectors.%s.%s', $type, TextUtil::convertCamelCaseToSeparator($name));
            $parameters[] = $config->get($configName, '');
        }

        $instance = $reflectionClass->newInstanceArgs($parameters);
        $instance->setClient($client);

        return $instance;
    }
}