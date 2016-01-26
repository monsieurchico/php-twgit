<?php

namespace NMR\Shell\Git\Filter;

/**
 * Interface PostFilterableInterface
 */
interface PostFilterableInterface
{
    /**
     * @param array $results
     *
     * @return array
     */
    function apply(array & $results);
}