<?php

namespace NMR\Shell\Git\Filter;

/**
 * Class BranchNameFilter
 */
class BranchNameFilter implements PostFilterableInterface
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
     * @param array $results
     */
    public function apply(array & $results)
    {
        foreach ($results as $index => $result) {
            if (false === strpos($result, $this->branch)) {
                unset($results[$index]);
            }
        }
    }
}