<?php

namespace Glavweb\RestBundle\Determiner\Action;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\UnitOfWork;
use Glavweb\RestBundle\Determiner\DeterminerHandler;
use Nelmio\ApiDocBundle\Extractor\ApiDocExtractor;
use Symfony\Bundle\FrameworkBundle\Routing\Router;

/**
 * Class ViewActionDeterminer
 * @package Glavweb\RestBundle\Determiner\Action
 */
class ViewActionDeterminer extends AbstractViewActionDeterminer
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
     * ViewActionDeterminer constructor.
     *
     * @param DeterminerHandler $determinerHandler
     * @param Registry $doctrine
     * @param Router $router
     * @param ApiDocExtractor $apiDocExtractor
     * @param string $scopeDir
     */
    public function __construct(DeterminerHandler $determinerHandler, Registry $doctrine, Router $router, ApiDocExtractor $apiDocExtractor, $scopeDir)
    {
        parent::__construct($router, $apiDocExtractor, $scopeDir);

        $this->determinerHandler = $determinerHandler;
        $this->doctrine          = $doctrine;
    }

    /**
     * @param string $scope
     * @return array
     */
    public function determineExpected($scope)
    {
        /** @var UnitOfWork $uow */
        $uow = $this->doctrine->getManager()->getUnitOfWork();
        $values = $uow->getOriginalEntityData($this->model);

        $handler = $this->determinerHandler;
        $fields       = $handler->getFields($this->modelClass);
        $associations = $handler->getAssociations($this->modelClass);
        $scopeConfig  = $this->getScopeConfig($scope);

        $expectedValuesForTest = [];
        foreach ($values as $fieldName => $value) {
            if (!array_key_exists($fieldName, $scopeConfig)) {
                continue;
            }

            if (isset($associations[$fieldName])) {
                if (method_exists($value, 'getId')) {
                    $expectedValuesForTest[$fieldName] = ['id' => $value->getId()];
                }

            } else {
                $fieldType = $fields[$fieldName];
                if ($fieldType == 'date' or $fieldType == 'datetime') {
                    $dateTime = $value instanceof \DateTime ? $value : new \DateTime($value);
                    $expectedValuesForTest[$fieldName] = $dateTime->format('c');

                } else {
                    $expectedValuesForTest[$fieldName] = $value;
                }
            }
        }

        return $expectedValuesForTest;
    }
}