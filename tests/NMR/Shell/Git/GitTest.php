<?php

namespace NMR\Shell\Git;

/**
 * Class GitTest
 */
class GitTest extends \PHPUnit_Framework_TestCase
{
    /** @var Git */
    protected $git;

    /**
     * @return Git
     */
    public function getGit()
    {
        if (is_null($this->git)) {
            $this->git = new Git();
            /** @var Logger $logger */
            $logger = $this->getMockBuilder('Logger')
                ->disableOriginalConstructor()
                ->getMock();
            $this->git->setLogger($logger);
        }

        return $this->git;
    }

    // -------

    /**
     * @return array
     */
    public function getDataProviderTags()
    {
        return [
            ['1.0.0', 1000000],
            ['12.34.123', 12034123],
            ['8.6.1', 8006001],
        ];
    }

    /**
     * @dataProvider getDataProviderTags
     */
    public function testConvertIntToTag($tag, $expected)
    {
        $int = $this->getGit()->convertTagToInt($tag);

        $this->assertEquals($expected, $int);
    }
}
