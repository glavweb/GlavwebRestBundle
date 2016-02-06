<?php

namespace Glavweb\RestBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Query\Expr\Comparison;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

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
     * DoctrineMatcher constructor.
     * @param Registry $doctrine
     */
    public function __construct(Registry $doctrine)
    {
        $this->doctrine = $doctrine;
    }

    /**
     * @param EntityRepository $repository
     * @param array            $fields
     * @param array            $orderings
     * @param int              $firstResult
     * @param int              $maxResults
     * @param string           $alias
     * @return DoctrineMatcherResult
     * @throws \Doctrine\DBAL\DBALException
     */
    public function matching(EntityRepository $repository, array $fields = array(), array $orderings = null, $firstResult = 0, $maxResults = null, $alias = 't')
    {
        $this->orderings   = $orderings;
        $this->firstResult = $firstResult;
        $this->maxResults  = $maxResults;
        $this->alias       = $alias;

        /** @var ClassMetadata $classMetadata */
        $classMetadata = $this->doctrine->getManager()->getClassMetadata($repository->getClassName());

        $fields = array_filter($fields, function ($value) {
            return !empty($value);
        });

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
                $associationMapping = $classMetadata->getAssociationMapping($field);
                $type               = $associationMapping['type'];

                if ($type == ClassMetadataInfo::MANY_TO_MANY && !is_array($value)) {
                    $queryBuilder
                        ->andWhere($expr->isMemberOf(":$field", "$alias.$field"))
                        ->setParameter($field,  $value)
                    ;

                } else {
                    $className = $classMetadata->getAssociationTargetClass($field);
                    $entity = $this->doctrine->getEntityManager()->find($className, $value);

                    $queryBuilder->andWhere($expr->eq($alias . '.' . $field, $entity->getId()));
                }

            }
        }

        $result = new DoctrineMatcherResult($queryBuilder, $orderings, $firstResult, $maxResults, $alias);

        return $result;
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