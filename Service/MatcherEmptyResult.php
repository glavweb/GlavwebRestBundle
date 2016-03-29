<?php

namespace Glavweb\RestBundle\Service;

/**
 * Class MatcherEmptyResult
 * @package Glavweb\RestBundle\Service
 */
class MatcherEmptyResult extends AbstractDoctrineMatcherResult
{
    /**
     * @return array
     */
    public function getList()
    {
        return [];
    }

    /**
     * @return int
     */
    public function getTotal()
    {
        return 0;
    }
}