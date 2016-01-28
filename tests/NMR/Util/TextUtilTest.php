<?php

namespace NMR\Util;

class TextUtilTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @return array
     */
    public function dataProviderGetNamespaceShortName()
    {
        return [
            ['MyClass', 'MyClass'],
            ['NMR\Namespace1\MyClass1', 'MyClass1'],
            ['NMR\Namespace1\Namespace2\MyClass2', 'MyClass2'],
            [new \stdClass(), 'stdClass'],
        ];
    }

    /**
     * @dataProvider dataProviderGetNamespaceShortName
     */
    public function testGetNamespaceShortName($class, $expected)
    {
        $this->assertEquals($expected, TextUtil::getNamespaceShortName($class));
    }

    /**
     * @return array
     */
    public function dataProviderGetConvertCamelCaseToSeparator()
    {
        return [
            ['MyString', '_', true, 'my_string'],
            ['MyStringNew', '_', false, 'My_String_New'],
            ['MyString', '-', true, 'my-string'],
            ['MyStringNew', '-', false, 'My-String-New'],
        ];
    }

    /**
     * @dataProvider dataProviderGetConvertCamelCaseToSeparator
     */
    public function testConvertCamelCaseToSeparator($string, $separator, $lowerCase, $expected)
    {
        $this->assertEquals($expected, TextUtil::convertCamelCaseToSeparator($string, $separator, $lowerCase));
    }


    /**
     * @return array
     */
    public function dataProviderSanitize()
    {
        return [
            ['My title with single \' quote', 'My title with single quote'],
            ['My title with double " quote', 'My title with double quote'],
            ['My title with slash /', 'My title with slash /'],
            ['My title with antislash \\', 'My title with antislash'],
            ['My title with multiple    blanks', 'My title with multiple blanks'],
        ];
    }

    /**
     * @dataProvider dataProviderSanitize
     */
    public function testSanitize($string, $expected)
    {
        $this->assertEquals($expected, TextUtil::sanitize($string));
    }
}
