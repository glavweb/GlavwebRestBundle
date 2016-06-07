<?php

namespace Glavweb\RestBundle\Test\Handler;


use Glavweb\DatagridBundle\Filter\Doctrine\Filter;

class ListFilterCaseHandler
{
    /**
     * @var array
     */
    private $caseDefinitions;

    /**
     * @var array
     */
    private $models;

    /**
     * ListFilterCaseHandler constructor.
     *
     * @param array $models
     */
    public function __construct(array $models)
    {
        $this->models = $models;
    }

    /**
     * @param string $filterName
     * @param string $filterValue
     * @param string $modelName
     * @param bool $checkNegative
     */
    public function addCase($filterName, $filterValue, $modelName, $checkNegative = false)
    {
        list($operator, $value) = Filter::guessOperator($filterValue);

        $negativeOperator = false;
        if ($checkNegative) {
            $negativeOperator = $this->getNegativeOperator($operator);
        }

        $this->caseDefinitions[$filterName] = [
            'value'            => $value,
            'operator'         => $operator,
            'modelName'        => $modelName,
            'negativeOperator' => $negativeOperator
        ];
    }

    /**
     * @return array
     */
    public function getCases()
    {
        $cases = [];
        foreach ($this->caseDefinitions as $filterName => $definition) {
            $modelId = $this->getModelId($definition['modelName']);

            // Filter positive
            $cases[$filterName] = [
                'values'    => [$filterName => $definition['operator'] . $definition['value']],
                'expected' => [
                    ['id' => $modelId]
                ]
            ];

            // Filter negative
            if ($definition['negativeOperator']) {
                $cases['negative__' . $filterName] = [
                    'values'   => [$filterName => $definition['negativeOperator'] . $definition['value']],
                    'expected' => []
                ];
            }
        }

        return $cases;
    }

    /**
     * @return array
     */
    public function guessCases()
    {
        $model = $this->model;
        if (!method_exists($model, 'getId')) {
            throw new \RuntimeException('Model should implement "getId" method.');
        }
        $modelId = $model->getId();

        $filters = $this->filters;
        $cases   = [];
        foreach ($filters as $filterName => $filterValue) {
            // Filter positive
            $cases[$filterName] = [
                'values'    => [$filterName => $filterValue],
                'expected' => [
                    ['id' => $modelId]
                ]
            ];

            // Filter negative
            list($operator, $value) = Filter::guessOperator($filterValue);
            $negativeOperator = $this->getNegativeOperator($operator);

            if ($negativeOperator) {
                $cases['negative_' . $filterName] = [
                    'values'   => [$filterName => $negativeOperator . $value],
                    'expected' => []
                ];
            }
        }

        return $cases;
    }

    /**
     * @param string $operator
     * @return string|null
     */
    private function getNegativeOperator($operator)
    {
        $negativeOperators = [
            Filter::EQ           => Filter::NEQ,
            Filter::NEQ          => Filter::EQ,

            Filter::GT           => Filter::LT,
            Filter::LT           => Filter::GT,

            Filter::GTE          => Filter::LTE,
            Filter::LTE          => Filter::GTE,

            Filter::IN           => Filter::NIN,
            Filter::NIN          => Filter::IN,

            Filter::CONTAINS     => Filter::NOT_CONTAINS,
            Filter::NOT_CONTAINS => Filter::CONTAINS,
        ];

        if (!isset($negativeOperators[$operator])) {
            return null;
        }

        return $negativeOperators[$operator];
    }

    /**
     * @param string $modelName
     * @return mixed
     */
    private function getModelId($modelName)
    {
        if (!isset($this->models[$modelName])) {
            throw new \RuntimeException(sprintf('Model %s not found.', $modelName));
        }

        $model = $this->models[$modelName];
        if (!method_exists($model, 'getId')) {
            throw new \RuntimeException('Model should implement "getId" method.');
        }

        return $model->getId();
    }

}