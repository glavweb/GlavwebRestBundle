<?php

namespace Glavweb\RestBundle\Determiner\Action;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\UnitOfWork;
use Glavweb\RestBundle\Determiner\DeterminerHandler;

/**
 * Class ViewActionDeterminer
 * @package Glavweb\RestBundle\Determiner\Action
 */
class ViewActionDeterminer
{
    /**
     * @var DeterminerHandler
     */
    protected $determinerHandler;

    /**
     * @var Registry
     */
    protected $doctrine;

    /**
     * @var object
     */
    protected $model;

    /**
     * @var string
     */
    protected $modelClass;

    /**
     * @var string
     */
    protected $viewScope;

    /**
     * ViewActionDeterminer constructor.
     *
     * @param DeterminerHandler $determinerHandler
     * @param Registry $doctrine
     */
    public function __construct(DeterminerHandler $determinerHandler, Registry $doctrine)
    {
        $this->determinerHandler = $determinerHandler;
        $this->doctrine          = $doctrine;
    }

    /**
     * @param object $model
     * @return $this
     */
    public function setModel($model)
    {
        $this->model      = $model;
        $this->modelClass = get_class($model);

        return $this;
    }

    /**
     * @param string $scope
     * @return $this
     */
    public function setViewScope($scope)
    {
        $this->viewScope = $scope;

        return $this;
    }

    /**
     * @return array
     */
    public function determineExpected()
    {
        /** @var UnitOfWork $uow */
        $uow = $this->doctrine->getManager()->getUnitOfWork();
        $values = $uow->getOriginalEntityData($this->model);

        $handler = $this->determinerHandler;
        $fields           = $handler->getFields($this->modelClass);
        $associations     = $handler->getAssociations($this->modelClass);
        $serializerGroups = $handler->getSerializerGroups($this->modelClass);

        $expectedValuesForTest = [];
        foreach ($values as $fieldName => $value) {
            $isViewScope =
                $this->viewScope &&
                isset($serializerGroups[$fieldName]) &&
                in_array($this->viewScope, $serializerGroups[$fieldName])
            ;

            if (!$isViewScope) {
                continue;
            }

            if (isset($associations[$fieldName]) && method_exists($value, 'getId')) {
                $expectedValuesForTest[$fieldName] = ['id' => $value->getId()];

            } else {
                $fieldType = $fields[$fieldName];
                if ($fieldType == 'date' or $fieldType == 'datetime') {
                    $dateTime = new \DateTime($value);
                    $expectedValuesForTest[$fieldName] = $dateTime->format('c');

                } else {
                    $expectedValuesForTest[$fieldName] = $value;
                }
            }
        }

        return $expectedValuesForTest;
    }
}