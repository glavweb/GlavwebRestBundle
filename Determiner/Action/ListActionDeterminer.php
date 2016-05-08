<?php

namespace Glavweb\RestBundle\Determiner\Action;

use Glavweb\DatagridBundle\Filter\Doctrine\Filter;

/**
 * Class ListActionDeterminer
 * @package Glavweb\RestBundle\Determiner\Action
 */
class ListActionDeterminer extends AbstractViewActionDeterminer
{
    /**
     * @var array
     */
    private $filters;

    /**
     * @param array $filters
     * @return $this
     */
    public function setFilters(array $filters = [])
    {
        $this->filters = $filters;

        return $this;
    }

    /**
     * @return array
     */
    public function determineCases()
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
}