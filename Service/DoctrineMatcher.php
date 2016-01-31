<?php

namespace Glavweb\RestBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Expr\Comparison;
use Symfony\Component\Form\AbstractType;

/**
 * Class DoctrineMatcher
 * @package Glavweb\RestBundle\Service
 */
class DoctrineMatcher
{
    const EQ        = '=';
    const NEQ       = '<>';
    const LT        = '<';
    const LTE       = '<=';
    const GT        = '>';
    const GTE       = '>=';
    const IN        = 'IN';
    const CONTAINS  = 'CONTAINS';

    /**
     * @var Registry
     */
    private $doctrine;

    /**
     * DoctrineMatcher constructor.
     * @param Registry $doctrine
     */
    public function __construct(Registry $doctrine)
    {
        $this->doctrine = $doctrine;
    }

    /**
     * @param EntityRepository  $repository
     * @param array             $fields
     * @param callable|\Closure $callback
     * @param string            $alias
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    public function matching(EntityRepository $repository, array $fields = array(), \Closure $callback = null, $alias = 't')
    {
        /** @var ClassMetadata $classMetadata */
        $classMetadata = $this->doctrine->getManager()->getClassMetadata($repository->getClassName());

        $fields = array_filter($fields, function ($value) {
            return !empty($value);
        });

        $sort   = array();
        $offset = 0;
        $limit  = null;

        if (isset($fields['_sort'])) {
            $sort = $fields['_sort'];
            unset($fields['_sort']);
        }

        if (isset($fields['_offset'])) {
            $offset = (int)$fields['_offset'];
            unset($fields['_offset']);
        }

        if (isset($fields['_limit'])) {
            $limit = $fields['_limit'];
            unset($fields['_limit']);
        }

        $queryBuilder = $repository->createQueryBuilder($alias);
        $expr = $queryBuilder->expr();

        foreach ($fields as $field => $value) {
            if ($classMetadata->hasField($field)) {
                $fieldType = $classMetadata->getTypeOfField($field);
                $reflectionClass = new \ReflectionClass(Type::getType($fieldType));

                $operator = null;
                if (is_array($value)) {
                    $operator = self::IN;
                }

                // if enum type
                if ($reflectionClass->isSubclassOf('\Fresh\DoctrineEnumBundle\DBAL\Types\AbstractEnumType')) {
                    if (!$operator) {
                        $operator = Comparison::EQ;
                    }

                    $value = preg_replace('/[^a-zA-Z0-9]+/', '', $value);
                }

                if (!$operator) {
                    list($operator, $value) = $this->separateOperator($value);
                }

                if ($operator == self::CONTAINS) {
                    $queryBuilder->andWhere($expr->like($alias . '.' . $field, $expr->literal("%$value%")));

                } elseif ($operator == self::IN) {
                    $queryBuilder->andWhere($expr->in($alias . '.' . $field, $value));

                } else {
                    $comparison = new Comparison($alias . '.' . $field, $operator, $expr->literal($value));
                    $queryBuilder->andWhere($comparison);
                }

            } elseif ($classMetadata->hasAssociation($field)) {
                $className = $classMetadata->getAssociationTargetClass($field);
                $entity = $this->getDoctrine()->getEntityManager()->find($className, $value);

                $queryBuilder->andWhere($expr->eq($alias . '.' . $field, $entity));
            }
        }

        if ($callback) {
            $callback($queryBuilder, $alias);
        }

//        $countQueryBuilder = clone $queryBuilder;
//        $count = $countQueryBuilder
//            ->select('COUNT(' . $alias . ')')
//            ->getQuery()
//            ->getSingleScalarResult()
//        ;
//
//        if ($offset < $count) {
//            $end = isset($limit) ? $offset + $limit : $count;
//            $end = $end > $count ? $count : $end;
//            $contentRange = $offset-$end/$count;
//
//            header("Content-Range: items $contentRange");
//        }

        $queryBuilder->setFirstResult($offset);
        $queryBuilder->setMaxResults($limit);

        foreach ($sort as $fieldName => $order) {
            $queryBuilder->addOrderBy($alias . '.' . $fieldName, $order);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * @param $value
     * @return array
     */
    private function separateOperator($value)
    {
        $operators = array(
            '<>' => self::NEQ,
            '<=' => self::LTE,
            '>=' => self::GTE,
            '<'  => self::LT,
            '>'  => self::GT,
            '='  => self::EQ,
        );

        if (preg_match('/^(?:\s*(<>|<=|>=|<|>|=))?(.*)$/', $value, $matches)) {
            $operator = isset($operators[$matches[1]]) ? $operators[$matches[1]] : self::CONTAINS;
            $value    = $matches[2];

        } else {
            $operator = self::CONTAINS;
        }

        return array($operator, $value);
    }
}