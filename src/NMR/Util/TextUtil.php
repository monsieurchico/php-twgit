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
     * @param bool   $lowerCase
     *
     * @return string
     */
    public static function convertCamelCaseToSeparator($string, $separator = '_', $lowerCase = true)
    {
        $convertedString = preg_replace_callback('@[a-z][A-Z]@', function (&$matches) use ($separator, $lowerCase) {
            return sprintf('%s%s%s', substr($matches[0], 0, 1), $separator, substr($matches[0], 1, 1));
        }, $string);

        return $lowerCase ? strtolower($convertedString) : $convertedString;
    }

    /**
     * @param $string
     *
     * @return string
     */
    public static function sanitize($string)
    {
        $string = str_replace(['"', "'", '\\'], ' ', $string);
        $string = preg_replace('/\s+/', ' ', $string);

        return trim($string);
    }
}