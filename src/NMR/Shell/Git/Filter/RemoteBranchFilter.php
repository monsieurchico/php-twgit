<?php

namespace NMR\Shell\Git\Filter;

/**
 * Class RemoteBranchFilter
 */
class RemoteBranchFilter implements PreFilterableInterface
{
    /**
     * @return string
     */
    public function toString()
    {
        return '-r';
    }
}