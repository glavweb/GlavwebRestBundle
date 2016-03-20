<?php

namespace Glavweb\RestBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Query\Expr\Comparison;
use Doctrine\ORM\QueryBuilder;
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
        $em = $this->doctrine->getManager();
        $classMetadata = $em->getClassMetadata($repository->getClassName());

        $fields = array_filter($fields, function ($value) {
            return !empty($value);
        });

        $queryBuilder = $repository->createQueryBuilder($alias);
        foreach ($fields as $field => $value) {
            if (strpos($field, '.') > 0) {
                $joins = explode('.', $field);
                $lastElement = $joins[count($joins) - 1];
                
                $joinAlias          = $alias;
                $joinClassMetadata  = $classMetadata;
                foreach ($joins as $joinFieldName) {
                    if ($joinClassMetadata->hasAssociation($joinFieldName)) {
                        $isLastElement = $joinFieldName == $lastElement;
                        if (!$isLastElement) {
                            $queryBuilder->join($joinAlias . '.' . $joinFieldName, $joinFieldName);

                            $joinAssociationMapping = $joinClassMetadata->getAssociationMapping($joinFieldName);
                            $joinClassName = $joinAssociationMapping['targetEntity'];

                            $joinClassMetadata = $em->getClassMetadata($joinClassName);
                            $joinAlias         = $joinFieldName;
                        }

                    } else {
                        break;
                    }
                }

                if (isset($joinFieldName)) {
                    $this->addFilter($queryBuilder, $joinClassMetadata, $joinFieldName, $value, $joinAlias);
                }

            } else {
                $this->addFilter($queryBuilder, $classMetadata, $field, $value, $alias);
            }
        }

        $result = new DoctrineMatcherResult($queryBuilder, $orderings, $firstResult, $maxResults, $alias);

        return $result;
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param ClassMetadata $classMetadata
     * @param string $field
     * @param mixed  $value
     * @param string $alias
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\Mapping\MappingException
     */
    private function addFilter(QueryBuilder $queryBuilder, ClassMetadata $classMetadata, $field, $value, $alias)
    {
        $expr = $queryBuilder->expr();
        
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
                    $operator = $value[0] == '!' ? Comparison::NEQ : Comparison::EQ;
                }
                
                $value = preg_replace('/[^a-zA-Z0-9_]+/', '', $value);
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

            if (in_array($type, [ClassMetadataInfo::MANY_TO_MANY, ClassMetadataInfo::ONE_TO_MANY])) {
                if (!is_array($value)) {
                    $queryBuilder
                        ->andWhere($expr->isMemberOf(":$field", "$alias.$field"))
                        ->setParameter($field,  $value)
                    ;
                } else {
                    $queryBuilder->join($alias . '.' . $field, $field);
                    $queryBuilder
                        ->andWhere($field . ' in (:' . $field. ')')
                        ->setParameter($field, $value)
                    ;
                }

            } else {
                $className = $classMetadata->getAssociationTargetClass($field);
                $entity = $this->doctrine->getManager()->find($className, $value);

                $queryBuilder->andWhere($expr->eq($alias . '.' . $field, $entity->getId()));
            }
        }
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