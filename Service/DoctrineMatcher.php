<?php

namespace Glavweb\RestBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Common\Annotations\Reader;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\NativeQuery;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr\Comparison;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\ORM\QueryBuilder;
use Glavweb\RestBundle\Mapping\Annotation\Access;
use Glavweb\RestBundle\Security\AccessHandler;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

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
     * @var AccessHandler
     */
    private $accessHandler;

    /**
     * @var AuthorizationCheckerInterface
     */
    private $authorizationChecker;

    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

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
     *
     * @param Registry $doctrine
     * @param AccessHandler $accessHandler
     * @param AuthorizationCheckerInterface $authorizationChecker
     * @param TokenStorageInterface $tokenStorage
     */
    public function __construct(Registry $doctrine, AccessHandler $accessHandler, AuthorizationCheckerInterface $authorizationChecker, TokenStorageInterface $tokenStorage)
    {
        $this->doctrine             = $doctrine;
        $this->accessHandler        = $accessHandler;
        $this->authorizationChecker = $authorizationChecker;
        $this->tokenStorage         = $tokenStorage;
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

        $queryBuilder = $this->doMatching($repository, $fields, $alias);
        $result = new DoctrineMatcherResult($queryBuilder, $orderings, $firstResult, $maxResults, $alias);

        return $result;
    }

    /**
     * @param EntityRepository $repository
     * @param array $fields
     * @param array $orderings
     * @param int $firstResult
     * @param int $maxResults
     * @param string $alias
     * @param $callback
     * @return DoctrineNativeSqlMatcherResult
     */
    public function matchingNativeSql(EntityRepository $repository, array $fields = array(), array $orderings = null, $firstResult = 0, $maxResults = null, $alias = 't', $callback)
    {
        $em           = $this->doctrine->getManager();
        $queryBuilder = $this->doMatching($repository, $fields, 't');
        $subQuery     = $this->buildSql($queryBuilder);
        $rsm          = $this->createResultSetMapping($repository->getClassName(), $alias);

        $query = $callback($subQuery, $rsm, $em);
        if (!$query instanceof NativeQuery) {
            throw new \RuntimeException('Callback must be return instance of Doctrine\ORM\NativeQuery.');
        }

        // Add conditions
        $this->addNativeConditions($fields, $rsm, $query);

        $orderings = $this->transformOrderingForNativeSql((array)$orderings, $rsm);

        $this->orderings   = $orderings;
        $this->firstResult = $firstResult;
        $this->maxResults  = $maxResults;
        $this->alias       = $alias;

        $result = new DoctrineNativeSqlMatcherResult($query, $this->orderings, $firstResult, $maxResults);

        return $result;
    }

    /**
     * @param string $class
     * @param string $alias
     * @return ResultSetMapping
     */
    protected function createResultSetMapping($class, $alias)
    {
        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();

        $rsm = new ResultSetMapping();
        $rsm->addEntityResult($class, $alias);

        $classMetaData = $em->getClassMetadata($class);
        $fieldNames = $classMetaData->getFieldNames();
        foreach ($fieldNames as $fieldName) {
            $rsm->addFieldResult($alias, $classMetaData->getColumnName($fieldName), $fieldName);
        }

        return $rsm;
    }

    /**
     * @param EntityRepository $repository
     * @param array $fields
     * @param string $alias
     * @return QueryBuilder
     * @throws \Doctrine\ORM\Mapping\MappingException
     */
    protected function doMatching(EntityRepository $repository, array $fields, $alias)
    {
        /** @var ClassMetadata $classMetadata */
        $em = $this->doctrine->getManager();
        $classMetadata = $em->getClassMetadata($repository->getClassName());

        $fields = array_filter($fields, function ($value) {
            return $value !== null;
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
                            $joinAlias = $joinFieldName;
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

        $class = $repository->getClassName();
        $masterViewRole = $this->accessHandler->getRole($class, 'VIEW');

        if (!$this->authorizationChecker->isGranted($masterViewRole)) {
            $securityConditions = [];
            $user = $this->tokenStorage->getToken()->getUser();

            $additionalRoles = $this->accessHandler->getAdditionalRoles($class);
            foreach ($additionalRoles as $additionalRoleName => $additionalRoleData) {
                $role = $this->accessHandler->getRole($class, 'VIEW', $additionalRoleName);

                if (isset($additionalRoleData['condition']) && $this->authorizationChecker->isGranted($role)) {
                    $securityConditions[] = strtr($additionalRoleData['condition'], [
                        '{{alias}}' => $alias,
                        '{{user}}'  => $user->getId(),
                    ]);
                }
            }

            if (!$securityConditions) {
                return null;

            } else {
                $expr = $queryBuilder->expr();
                $queryBuilder->andWhere($expr->orX()->addMultiple($securityConditions));
            }
        }

        return $queryBuilder;
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param bool         $replaceSelect
     * @return string
     */
    public function buildSql(QueryBuilder $queryBuilder, $replaceSelect = true)
    {
        $sql = $queryBuilder->getQuery()->getSQL();

        if ($replaceSelect) {
            $result = preg_match('/SELECT .*? FROM [\w]* ([^ ]*)/', $sql, $matches);
            if (!$result) {
                throw new \RuntimeException('Alias not found.');
            }

            $alias = $matches[1];
            $sql = preg_replace('/SELECT .*? FROM/', 'SELECT ' . $alias . '.* FROM', $sql);
        }

        return $sql;
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

    /**
     * @param array $orderings
     * @param ResultSetMapping $rsm
     * @return array
     */
    private function transformOrderingForNativeSql(array $orderings, ResultSetMapping $rsm)
    {
        $scalarMappings = $rsm->scalarMappings;
        foreach ($orderings as $fieldName => $sort) {
            if (($alias = array_search($fieldName, $scalarMappings)) !== false) {
                unset($orderings[$fieldName]);
                $orderings[$alias] = $sort;
            }
        }

        return $orderings;
    }

    /**
     * @param array $fields
     * @param ResultSetMapping $rsm
     * @param NativeQuery $query
     */
    private function addNativeConditions(array $fields, ResultSetMapping $rsm, NativeQuery $query)
    {
        $expr = new Query\Expr();

        $whereParts = [];
        $scalarMappings = $rsm->scalarMappings;
        foreach ($scalarMappings as $fieldAlias => $fieldName) {
            if (!isset($fields[$fieldName])) {
                continue;
            }
            $value = $fields[$fieldName];

            $operator = null;
            if (is_array($value)) {
                $operator = self::IN;
            }

            if (!$operator) {
                list($operator, $value) = $this->separateOperator($value);
            }

            if ($operator == self::CONTAINS) {
                $whereParts[] = $expr->like($fieldAlias, $expr->literal("%$value%"));

            } elseif ($operator == self::IN) {
                $whereParts[] = $expr->in($fieldAlias, $value);

            } else {
                $whereParts[] = new Comparison($fieldAlias, $operator, $expr->literal($value));
            }
        }

        $uniqueAlias = uniqid();
        $sql = 'SELECT ' . $uniqueAlias . '.* FROM (' . $query->getSQL() . ') as ' . $uniqueAlias;

        if ($whereParts) {
            $sql .= ' WHERE ' . implode(' AND ', $whereParts);
        }

        $query->setSQL($sql);
    }
}