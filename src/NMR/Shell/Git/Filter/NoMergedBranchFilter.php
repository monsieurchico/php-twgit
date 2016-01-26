<?php

namespace NMR\Shell\Git\Filter;

/**
 * Class NoMergedBranchFilter
 */
class NoMergedBranchFilter implements PreFilterableInterface
{
    /** @var string */
    protected $branch;

    /**
     * NoMergedBranchFilter constructor.
     *
     * @param string $branch
     */
    public function __construct($branch)
    {
        $this->branch = $branch;
    }

    /**
     * @return string
     */
    public function toString()
    {
        return sprintf('--no-merged %s', $this->branch);
    }
}