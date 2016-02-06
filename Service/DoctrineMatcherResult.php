<?php

namespace Glavweb\RestBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Expr\Comparison;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class DoctrineMatcherResult
 * @package Glavweb\RestBundle\Service
 */
class DoctrineMatcherResult
{
    /**
     * @var QueryBuilder
     */
    private $queryBuilder;

    /**
     * @var array
     */
    private $orderings;

    /**
     * @var int
     */
    private $firstResult;

    /**
     * @var int
     */
    private $maxResults;

    /**
     * @var string
     */
    private $alias;

    /**
     * @param QueryBuilder $queryBuilder
     * @param array            $orderings
     * @param int              $firstResult
     * @param int              $maxResults
     * @param string           $alias
     * @param string $alias
     */
    public function __construct(QueryBuilder $queryBuilder, array $orderings = null, $firstResult = 0, $maxResults = null, $alias = 't')
    {
        $this->queryBuilder = $queryBuilder;
        $this->orderings   = (array)$orderings;
        $this->firstResult = (int)$firstResult;
        $this->maxResults  = $maxResults;
        $this->alias       = $alias;
    }

    /**
     * @return array
     */
    public function getList()
    {
        $queryBuilder = $this->getQueryBuilder();
        $alias        = $this->getAlias();
        $firstResult  = $this->getFirstResult();
        $maxResults   = $this->getMaxResults();
        $orderings    = $this->getOrderings();

        $queryBuilder->setFirstResult($firstResult);
        $queryBuilder->setMaxResults($maxResults);

        foreach ($orderings as $fieldName => $order) {
            $queryBuilder->addOrderBy($alias . '.' . $fieldName, $order);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * @return mixed
     */
    public function getTotal()
    {
        $totalQueryBuilder = clone $this->getQueryBuilder();
        $alias             = $this->getAlias();

        $total = $totalQueryBuilder
            ->select('COUNT(' . $alias . ')')
            ->getQuery()
            ->getScalarResult()
        ;

        return $total;
    }

    /**
     * @return QueryBuilder
     */
    public function getQueryBuilder()
    {
        return $this->queryBuilder;
    }

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
     * @return string
     */
    public function getAlias()
    {
        return $this->alias;
    }
}