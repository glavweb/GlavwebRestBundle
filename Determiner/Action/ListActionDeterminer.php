<?php

namespace Glavweb\RestBundle\Determiner\Action;

use Doctrine\ORM\UnitOfWork;
use Doctrine\Bundle\DoctrineBundle\Registry;
use FOS\RestBundle\Controller\Annotations\QueryParam;
use FOS\RestBundle\Request\ParamReaderInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class ListActionDeterminer
 * @package Glavweb\RestBundle\Determiner\Action
 */
class ListActionDeterminer
{
    /**
     * @var Registry
     */
    protected $doctrine;

    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var ParamReaderInterface
     */
    protected $paramFetcherReader;

    /**
     * @var array
     */
    private $models = [];

    /**
     * ListActionDeterminer constructor.
     *
     * @param Registry $doctrine
     * @param RouterInterface $router
     * @param ParamReaderInterface $paramFetcherReader
     */
    public function __construct(Registry $doctrine, RouterInterface $router, ParamReaderInterface $paramFetcherReader)
    {
        $this->doctrine           = $doctrine;
        $this->router             = $router;
        $this->paramFetcherReader = $paramFetcherReader;
    }

    /**
     * @param array $models
     * @return $this
     */
    public function setModels($models)
    {
        $this->models = $models;

        return $this;
    }

    /**
     * @param string $uri
     * @return array
     */
    public function determineCases($uri)
    {
        /** @var UnitOfWork $uow */
        $uow = $this->doctrine->getManager()->getUnitOfWork();
        $filters = $this->getFilters($uri);

        $cases = [];
        foreach ($this->models as $model) {
            if (!method_exists($model, 'getId')) {
                throw new \RuntimeException('Model should implement "getId" method.');
            }

            $modelId = $model->getId();
            $values = $uow->getOriginalEntityData($model);
            foreach ($values as $fieldName => $value) {
                if (!isset($filters[$fieldName])) {
                    continue;
                }

                if ($value instanceof \DateTime) {
                    $value = $value->format('c');
                }

                $key = null;
                if (isset($cases[$fieldName])) {
                    $key = $this->findValueInCase($cases[$fieldName], $value);
                }

                if (!$key) {
                    $cases[$fieldName] = [
                        'value'    => $value,
                        'expected' => [
                            ['id' => $modelId]
                        ]
                    ];
                } else {
                    $cases[$fieldName][$key]['expected'][] = ['id' => $modelId];
                }
            }
        }

        return $cases;
    }

    /**
     * @param string $uri
     * @return array
     */
    private function getFilters($uri)
    {
        $restParams = $this->getRestParams($uri);

        return array_map(function ($restParam) {
            /** @var QueryParam $restParam */
            return $restParam->name;
        }, $restParams);
    }

    /**
     * @param string $uri
     * @return QueryParam[]
     */
    private function getRestParams($uri)
    {
        $routeParams = $this->router->match($uri);
        list($controller, $method) = explode('::', $routeParams['_controller']);

        $restParams = $this->paramFetcherReader->read(
            new \ReflectionClass($controller),
            $method
        );

        return $restParams;
    }

    /**
     * @param array $modelsValues
     * @param string $value
     * @return int|null|string
     */
    private function findValueInCase(array $modelsValues, $value)
    {
        foreach ($modelsValues as $key => $data) {
            if ($modelsValues['value'] == $value) {
                return $key;
            }
        }

        return null;
    }
}