<?php

namespace NMR\Util;

/**
 * Class TextUtil
 */
class TextUtil
{
    /**
     * @param string $class
     *
     * @return string
     */
    public static function getNamespaceShortName($class)
    {
        if (is_object($class)) {
            $class = get_class($class);
        }
        $x = explode('\\', $class);
        return end($x);
    }

    /**
     * @param string $string
     * @param string $separator
     *
     * @return string
     */
    public static function convertCamelCaseToSeparator($string, $separator = '_')
    {
        return preg_replace_callback('@([A-Z])@', function (&$matches) use ($separator) {
            return sprintf('%s%s', $separator, strtolower($matches[1]));
        }, $string);
    }

    /**
     * @param $string
     *
     * @return string
     */
    public static function sanitize($string)
    {
        return str_replace(['"', "'", '/', '\\'], '', $string);
    }
}