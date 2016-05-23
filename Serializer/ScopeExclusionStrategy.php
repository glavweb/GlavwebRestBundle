<?php

namespace Glavweb\RestBundle\Serializer;

use JMS\Serializer\Context;
use JMS\Serializer\Exclusion\ExclusionStrategyInterface;
use JMS\Serializer\Metadata\ClassMetadata;
use JMS\Serializer\Metadata\PropertyMetadata;

/**
 * Class ScopeExclusionStrategy
 * @package Glavweb\RestBundle\Scope
 */
class ScopeExclusionStrategy implements ExclusionStrategyInterface
{
    /**
     * @var array
     */
    private $scope;

    /**
     * ScopeExclusionStrategy constructor.
     *
     * @param array $scope
     */
    public function __construct(array $scope)
    {
        $this->scope = $scope;
    }

    /**
     * @param ClassMetadata $metadata
     * @param Context $context
     * @return bool
     */
    public function shouldSkipClass(ClassMetadata $metadata, Context $context)
    {
        return false;
    }

    /**
     * @param PropertyMetadata $property
     * @param Context $context
     * @return bool
     */
    public function shouldSkipProperty(PropertyMetadata $property, Context $context)
    {
        $plainFields = $this->transformToPlainArray($this->scope);

        if ($property->name == 'id') {
            return false;
        }

        $stack = $this->getStackAsString($context->getMetadataStack());
        if ($stack) {
            $propertyInStack = $stack . '.' . $property->name;

            return !in_array($propertyInStack, $plainFields);
        }

        return !in_array($property->name, $plainFields);
    }

    /**
     * @param array  $array
     * @param string $globalKey
     * @param array  $result
     * @return array
     */
    private function transformToPlainArray(array $array, $globalKey = null, &$result = [])
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result[] = $globalKey ? $globalKey . '.' . $key : $key;

                $globalKey = $globalKey ? $globalKey . '.' . $key : $key;
                $this->transformToPlainArray($value, $globalKey, $result);
                $globalKey = null;

            } else {
                $result[] = $globalKey ? $globalKey . '.' . $key : $key;
            }
        }

        return $result;
    }

    /**
     * @param \SplStack $metadataStack
     * @return null|string
     */
    protected function getStackAsString(\SplStack $metadataStack)
    {
        if ($metadataStack->count() > 1) {
            $propertyNames = [];
            foreach ($metadataStack as $metadata) {
                if ($metadata instanceof PropertyMetadata) {
                    $propertyNames[] = $metadata->name;
                }
            }
            $propertyNames = array_reverse($propertyNames);

            return implode('.', $propertyNames);
        }

        return null;
    }
}