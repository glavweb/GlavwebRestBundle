<?php

namespace Glavweb\RestBundle\Test\Guesser\Action;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\UnitOfWork;
use Glavweb\RestBundle\Test\Guesser\GuesserHandler;
use Nelmio\ApiDocBundle\Extractor\ApiDocExtractor;
use Symfony\Bundle\FrameworkBundle\Routing\Router;

/**
 * Class ViewActionGuesser
 * @package Glavweb\RestBundle\Test\Guesser\Action
 */
class ViewActionGuesser extends AbstractViewActionGuesser
{
    /**
     * @var GuesserHandler
     */
    protected $guesserHandler;

    /**
     * @var Registry
     */
    protected $doctrine;

    /**
     * ViewActionGuesser constructor.
     *
     * @param GuesserHandler $guesserHandler
     * @param Registry $doctrine
     * @param Router $router
     * @param ApiDocExtractor $apiDocExtractor
     * @param string $scopeDir
     */
    public function __construct(GuesserHandler $guesserHandler, Registry $doctrine, Router $router, ApiDocExtractor $apiDocExtractor, $scopeDir)
    {
        parent::__construct($router, $apiDocExtractor, $scopeDir);

        $this->guesserHandler = $guesserHandler;
        $this->doctrine          = $doctrine;
    }

    /**
     * @param string $scope
     * @return array
     */
    public function guessExpected($scope)
    {
        /** @var UnitOfWork $uow */
        $uow = $this->doctrine->getManager()->getUnitOfWork();
        $values = $uow->getOriginalEntityData($this->model);

        $handler = $this->guesserHandler;
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