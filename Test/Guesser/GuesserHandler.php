<?php

namespace Glavweb\RestBundle\Test\Guesser;

use Doctrine\Bundle\DoctrineBundle\Registry;
use JMS\Serializer\Metadata\Driver\DoctrineTypeDriver;
use Nelmio\Alice\Fixtures\Parser\Parser;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Nelmio\Alice\Fixtures\Parser\Methods\Yaml as YamlMethod;

/**
 * Class GuesserHandler
 * @package Glavweb\RestBundle\Test\Guesser
 */
class GuesserHandler
{
    /**
     * @var array
     */
    private static $cache = [];

    /**
     * @var array
     */
    private static $serializerMetadataCache = [];

    /**
     * @var KernelInterface
     */
    protected $kernel;

    /**
     * @var Registry
     */
    protected $doctrine;

    /**
     * @var FormFactory
     */
    protected $formFactory;

    /**
     * @var DoctrineTypeDriver
     */
    protected $metadataDriver;

    /**
     * TestPostRestAction constructor.
     *
     * @param KernelInterface $kernel
     * @param Registry $doctrine
     * @param FormFactory $formFactory
     * @param DoctrineTypeDriver $metadataDriver
     */
    public function __construct(KernelInterface $kernel, Registry $doctrine, FormFactory $formFactory, DoctrineTypeDriver $metadataDriver)
    {
        $this->kernel           = $kernel;
        $this->doctrine         = $doctrine;
        $this->formFactory      = $formFactory;
        $this->metadataDriver   = $metadataDriver;
    }

    /**
     * @param string $modelClass
     * @return array
     * @throws \Doctrine\ORM\Mapping\MappingException
     */
    public function getFields($modelClass)
    {
        if (!isset(self::$cache['fields'][$modelClass])) {
            /** @var \Doctrine\ORM\Mapping\ClassMetadata $metadata */
            $metadata = $this->doctrine->getManager()->getClassMetadata($modelClass);

            $fields = array();
            $fieldNames = $metadata->getFieldNames();
            foreach ($fieldNames as $fieldName) {
                $fieldMapping = $metadata->getFieldMapping($fieldName);
                $type = $fieldMapping['type'];

                $fields[$fieldName] = $type;
            }

            self::$cache['fields'][$modelClass] = $fields;
        }

        return self::$cache['fields'][$modelClass];
    }

    /**
     * @param string $modelClass
     * @return array
     */
    public function getAssociations($modelClass)
    {
        if (!isset(self::$cache['associations'][$modelClass])) {
            /** @var \Doctrine\ORM\Mapping\ClassMetadata $metadata */
            $metadata = $this->doctrine->getManager()->getClassMetadata($modelClass);

            $associationFields   = [];
            $associationMappings = $metadata->getAssociationMappings();
            foreach ($associationMappings as $associationMapping) {
                $fieldName = $associationMapping['fieldName'];
                $type      = $associationMapping['type'];

                $associationFields[$fieldName] = [
                    'type' => $type,
                ];
            }

            self::$cache['associations'][$modelClass] = $associationFields;
        }

        return self::$cache['associations'][$modelClass];
    }

    /**
     * @param string $modelClass
     * @param string $fixturePath
     * @param string $fixtureKey
     * @return array
     */
    public function getFixtureValues($modelClass, $fixturePath, $fixtureKey)
    {
        $cacheKey = md5($modelClass . '__' . $fixturePath  . '__' .  $fixtureKey);

        if (!isset(self::$cache['fixtureValues'][$cacheKey])) {
            $fields        = $this->getFields($modelClass);
            $fixtureData   = $this->getFixtureData($modelClass, $fixturePath, $fixtureKey);
            $fixtureValues = array();

            foreach ($fields as $fieldName => $fieldType) {
                $fixtureValue = isset($fixtureData[$fieldName]) ? ValueUtil::fixture($fixtureData[$fieldName]) : '' ;

                if ($fixtureValue) {
                    $fixtureValues[$fieldName] = $fixtureValue;
                }
            }

            self::$cache['fixtureValues'][$cacheKey] = $fixtureValues;
        }

        return self::$cache['fixtureValues'][$cacheKey];
    }

    /**
     * @param string $modelClass
     * @param FormTypeInterface $formType
     * @return array
     */
    public function getFormElements($modelClass, FormTypeInterface $formType)
    {
        if (!isset(self::$cache['formElements'][$modelClass])) {
            $form = $this->formFactory->create($formType);
            $formElements = $form->all();

            $elements = [];
            foreach ($formElements as $formElement) {
                $name = $formElement->getName();
                $type = $formElement->getConfig()->getType()->getName();

                $elements[$name] = $type;
            }

            self::$cache['formElements'][$modelClass] = $elements;
        }

        return self::$cache['formElements'][$modelClass];
    }


    /**
     * @param string $modelClass
     * @return array
     */
    public function getSerializerGroups($modelClass)
    {
        $reflectionClass = new \ReflectionClass($modelClass);
        $properties = $reflectionClass->getProperties();

        $fields = [];
        foreach ($properties as $property) {
            $fields[$property->getName()] = $this->getSerializerGroupsByProperty($property);
        }

        return $fields;
    }

    /**
     * @param \ReflectionProperty $property
     * @return array
     */
    private function getSerializerGroupsByProperty(\ReflectionProperty $property)
    {
        $metadata = $this->serializerMetadataByClass($property->class);

        if (isset($metadata->propertyMetadata[$property->name])) {
            return $metadata->propertyMetadata[$property->name]->groups;
        }

        return [];
    }

    /**
     * @param $class
     * @return \JMS\Serializer\Metadata\ClassMetadata|\Metadata\ClassMetadata
     */
    private function serializerMetadataByClass($class)
    {
        if (!isset(self::$serializerMetadataCache[$class])) {
            $reflectionClass = new \ReflectionClass($class);
            $metadata = $this->metadataDriver->loadMetadataForClass($reflectionClass);

            self::$serializerMetadataCache[$class] = $metadata;
        }

        return self::$serializerMetadataCache[$class];
    }

    /**
     * @param string $modelClass
     * @param string $fixturePath
     * @param string $fixtureKey
     * @return array|mixed
     */
    private function getFixtureData($modelClass, $fixturePath, $fixtureKey)
    {
        $parser = new Parser([new YamlMethod()]);

        $file = $this->kernel->locateResource($fixturePath);
        $data = $parser->parse($file);

        if (isset($data[$modelClass])) {
            $key = $fixtureKey;
            if (isset($data[$modelClass][$key])) {
                return $data[$modelClass][$key];
            }

            return current($data[$modelClass]);
        }

        return [];
    }
}