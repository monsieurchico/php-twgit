<?php

namespace NMR\Shell\Git\Filter;

/**
 * Class MergedBranchFilter
 */
class MergedBranchFilter implements PreFilterableInterface
{
    /** @var string */
    protected $branch;

    /**
     * MergedBranchFilter constructor.
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
        return sprintf('--merged %s', $this->branch);
    }
}