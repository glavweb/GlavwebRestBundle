<?php

namespace Glavweb\RestBundle\Service;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\NativeQuery;
use Doctrine\ORM\Query;

/**
 * Class DoctrineNativeSqlMatcherResult
 * @package Glavweb\RestBundle\Service
 */
class DoctrineNativeSqlMatcherResult extends AbstractDoctrineMatcherResult
{
    /**
     * @var NativeQuery
     */
    protected $query;

    /**
     * @var NativeQuery
     */
    protected $queryCount;

    /**
     * @param NativeQuery $query
     * @param array $orderings
     * @param int $firstResult
     * @param int $maxResults
     */
    public function __construct(NativeQuery $query, array $orderings = null, $firstResult = 0, $maxResults = null)
    {
        $this->query       = $query;
        $this->queryCount  = clone $query;
        $this->queryCount->setParameters($query->getParameters());

        $this->orderings   = (array)$orderings;
        $this->firstResult = (int)$firstResult;
        $this->maxResults  = $maxResults;
    }

    /**
     * @return array
     */
    public function getList()
    {
        $query = $this->query;
        $sql = $query->getSQL();
        
        $orderings = $this->getOrderings();
        if ($orderings) {
            $orderParts = [];
            foreach ($orderings as $fieldName => $sort) {
                $orderParts[] = $fieldName . ' ' . $sort;
            }

            $sql .= ' ORDER BY ' . implode(',', $orderParts);
        }

        $limit  = $this->getMaxResults();
        if ($limit) {
            $sql .= ' LIMIT ' . $limit;
        }

        $offset = $this->getFirstResult();
        if ($offset) {
            $sql .= ' OFFSET ' . $offset;
        }

        $query->setSQL($sql);

        return $query->getResult($this->getHydrationMode());
    }

    /**
     * @return int
     */
    public function getTotal()
    {
        $query = $this->queryCount;

        $sql = $query->getSQL();
        $sql = preg_replace('/SELECT .*? FROM/', 'SELECT COUNT(*) as count FROM', $sql, 1);
        $query->setSQL($sql);

        $rsm = new Query\ResultSetMapping();
        $rsm->addScalarResult('count', 'count');
        $query->setResultSetMapping($rsm);

        return (int)$query->getSingleScalarResult();
    }
}