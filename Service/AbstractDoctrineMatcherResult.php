<?php

namespace Glavweb\RestBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr\Comparison;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class AbstractDoctrineMatcherResult
 * @package Glavweb\RestBundle\Service
 */
abstract class AbstractDoctrineMatcherResult
{
    /**
     * @var array
     */
    protected $orderings;

    /**
     * @var int
     */
    protected $firstResult;

    /**
     * @var int
     */
    protected $maxResults;

    /**
     * @var int|string
     */
    protected $hydrationMode = AbstractQuery::HYDRATE_OBJECT;

    /**
     * @return array
     */
    abstract public function getList();

    /**
     * @return mixed
     */
    abstract public function getTotal();

    /**
     * @return array
     */
    public function getOrderings()
    {
        return $this->orderings;
    }

    /**
     * @return int
     */
    public function getFirstResult()
    {
        return $this->firstResult;
    }

    /**
     * @return int
     */
    public function getMaxResults()
    {
        return $this->maxResults;
    }

    /**
     * @return int|string
     */
    public function getHydrationMode()
    {
        return $this->hydrationMode;
    }

    /**
     * @param int|string $hydrationMode
     */
    public function setHydrationMode($hydrationMode)
    {
        $this->hydrationMode = $hydrationMode;
    }
}