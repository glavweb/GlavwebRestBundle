<?php

namespace Glavweb\RestBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
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
     * @return array
     * @param int $hydrationMode
     */
    abstract public function getList($hydrationMode = AbstractQuery::HYDRATE_OBJECT);

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
}