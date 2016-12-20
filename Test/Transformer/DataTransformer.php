<?php

namespace Glavweb\RestBundle\Test\Transformer;

/**
 * Class DataTransformer
 * @package Glavweb\RestBundle\Transformer
 */
class DataTransformer
{
    /**
     * @var array
     */
    private $expectedData;

    /**
     * @var array
     */
    private $actualData;

    /**
     * @var bool
     */
    private $isPrepared = false;

    /**
     * TestDataHandler constructor.
     * @param array $expectedData
     * @param array $actualData
     */
    public function __construct(array $expectedData, array $actualData)
    {
        $this->expectedData = $expectedData;
        $this->actualData   = $actualData;
    }

    /**
     * @return array
     */
    public function getExpectedData()
    {
        $this->prepareData();

        return $this->expectedData;
    }

    /**
     * @return array
     */
    public function getActualData()
    {
        $this->prepareData();

        return $this->actualData;
    }

    /**
     * @return void
     */
    private function prepareData()
    {
        if ($this->isPrepared) {
            return;
        }

        $expectedData = $this->expectedData;
        $actualData   = $this->actualData;

        $this->transformData($expectedData, $actualData);
        $this->clearActualData($expectedData, $actualData);

        $this->expectedData = $expectedData;
        $this->actualData   = $actualData;
        $this->isPrepared   = true;
    }

    /**
     * @param array $expected
     * @param array $actual
     * @return mixed
     */
    private function transformData(&$expected, &$actual)
    {
        foreach ($expected as $key => $value) {
            if (is_array($value)) {
                if (isset($actual[$key])) {
                    $this->transformData($expected[$key], $actual[$key]);
                }

                continue;
            }

            if ($value === '{ignore}') {
                if (isset($actual[$key])) {
                    unset($expected[$key]);
                    unset($actual[$key]);
                }

                continue;
            }

            if ($value === '{string}') {
                $checkActualValue = isset($actual[$key]) && is_string($actual[$key]);
                if ($checkActualValue) {
                    unset($expected[$key]);
                    unset($actual[$key]);
                }

                continue;
            }

            if ($value === '{integer}') {
                $checkActualValue = isset($actual[$key]) && is_numeric($actual[$key]);
                if ($checkActualValue) {
                    unset($expected[$key]);
                    unset($actual[$key]);
                }

                continue;
            }

            if ($value === '{array}') {
                $checkActualValue = isset($actual[$key]) && is_array($actual[$key]);
                if ($checkActualValue) {
                    unset($expected[$key]);
                    unset($actual[$key]);
                }

                continue;
            }

            if ($value === '{date}') {
                $checkActualValue = isset($actual[$key]) && !empty($actual[$key]) && new \DateTime($actual[$key]);

                if ($checkActualValue) {
                    unset($expected[$key]);
                    unset($actual[$key]);
                }

                continue;
            }
        }
    }

    /**
     * @param array $expected
     * @param array $actual
     */
    private function clearActualData(array &$expected, array &$actual)
    {
        foreach ($actual as $key => $value) {
            if (!isset($expected[$key])) {
                // is collection
                $isCollection = is_numeric($key) && is_array($value);
                if (!$isCollection) {
                    unset($actual[$key]);
                }

                continue;
            }

            if (is_array($value) && is_array($expected[$key])) {
                $this->clearActualData($expected[$key], $actual[$key]);
            }
        }
    }
}
