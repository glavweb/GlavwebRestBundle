<?php

namespace Glavweb\RestBundle\Test\Guesser;

use Doctrine\Common\Inflector\Inflector;
use Doctrine\DBAL\Types\Type;
use Fresh\DoctrineEnumBundle\DBAL\Types\AbstractEnumType;

/**
 * Class ValueUtil
 * @package Glavweb\RestBundle\Test\Guesser
 */
class ValueUtil
{
    /**
     * @param string $field
     * @return mixed
     */
    public static function fixture($field)
    {
        if (is_array($field)) {
            $count = count($field);
            $key = rand(0, $count - 1);

            return $field[$key];
        }

        $matches = [];
        $isDate = is_string($field) && preg_match('/\<\(new \\\DateTime\(\'(.*)\'\)\)\>/', $field, $matches);
        if ($isDate) {
            $field = $matches[1];
        }

        if (strpos($field, '<') === 0) {
            return '';
        }

        $field = str_replace("'", '"', $field);

        return $field;
    }

    /**
     * @param string $fieldType
     * @param string $defaultName
     * @return int|string
     */
    public static function newValue($fieldType, $defaultName = 'name')
    {
        if ($fieldType == 'integer') {
            $value = rand(1, 1000);

        } elseif ($fieldType == 'datetime') {
            $date = date('Y-m-d H:i', rand(time() - 604800, time()));
            $value = "<(new \DateTime('" . $date . "'))>";

        } elseif ($fieldType == 'date') {
            $date = date('Y-m-d', rand(time() - 604800, time()));
            $value = "<(new \DateTime('" . $date . "'))>";

        } elseif ($fieldType == 'boolean') {
            $value = rand(0, 1) ? 'true' : 'false';

        } else {
            $value = 'Some ' . str_replace('_', ' ', Inflector::tableize($defaultName));
        }

        return $value;
    }

    /**
     * @param string $value
     * @param string $fieldType
     * @param string $additional
     * @return mixed
     */
    public static function modifyValue($value, $fieldType, $additional = 'new')
    {
        if ($fieldType == 'boolean') {
            return $value;
//            $value = $value ? 'false' : 'true';

        } elseif (is_numeric($value)) {
            $value = $value + rand(1, 100);

            // is date
        } elseif ($fieldType == 'date' || $fieldType == 'datetime') {
            $date = $value;
            if (!$value instanceof \DateTime) {
                $date = new \DateTime($value);
            }

            $value = $date->modify('+1 day')->format('Y-m-d');

        } else {
            $reflectionClass = new \ReflectionClass(Type::getType($fieldType));
            if ($reflectionClass->isSubclassOf('\Fresh\DoctrineEnumBundle\DBAL\Types\AbstractEnumType')) {
                $value = self::getEnumValue($reflectionClass, $value);

            } else {
                $value = $additional . ' ' . $value;
            }
        }

        return $value;
    }

    /**
     * @param \ReflectionClass $reflectionClass
     * @param string $value
     * @return mixed
     */
    public static function getEnumValue(\ReflectionClass $reflectionClass, $value)
    {
        /** @var AbstractEnumType $enumClass */
        $enumClass = $reflectionClass->getName();
        $values    = $enumClass::getValues();

        // drop current value
        $currentKey = array_search($value, $values);
        if ($currentKey !== false) {
            unset($values[$currentKey]);
            $values = array_values($values);
        }

        $countValues = count($values);
        if ($countValues) {
            $numValue = rand(0, $countValues - 1);
            $value = $values[$numValue];
        }

        return $value;
    }

    /**
     * @param string $formTypeName
     * @return string
     */
    public static function getFieldTypeByFormFype($formTypeName)
    {
        switch ($formTypeName) {
            case 'integer' :
                return 'integer';

            case 'text' :
                return 'string';

            case 'textarea' :
                return 'text';

            default:
                return null;
        }
    }
}