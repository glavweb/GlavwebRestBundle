<?php

namespace Glavweb\RestBundle\Determiner\Action;

use Glavweb\RestBundle\Determiner\DeterminerHandler;
use Glavweb\RestBundle\Determiner\ValueUtil;
use Glavweb\RestBundle\Faker\FileFaker;
use Symfony\Component\Form\FormTypeInterface;

/**
 * Class CreateActionDeterminer
 * @package Glavweb\RestBundle\Determiner
 */
class CreateActionDeterminer
{
    /**
     * @var DeterminerHandler
     */
    protected $determinerHandler;

    /**
     * @var FileFaker
     */
    protected $fileFaker;

    /**
     * @var string
     */
    protected $modelClass;

    /**
     * @var FormTypeInterface
     */
    protected $formType;

    /**
     * @var string
     */
    protected $fixturePath;

    /**
     * @var string
     */
    protected $fixtureKey;

    /**
     * @var string
     */
    protected $viewScope;

    /**
     * PostActionDeterminer constructor.
     *
     * @param DeterminerHandler $determinerHandler
     * @param FileFaker $fileFaker
     */
    public function __construct(DeterminerHandler $determinerHandler, FileFaker $fileFaker)
    {
        $this->determinerHandler = $determinerHandler;
        $this->fileFaker         = $fileFaker;
    }

    /**
     * @param string $modelClass
     * @return $this
     */
    public function setModelClass($modelClass)
    {
        $this->modelClass = $modelClass;

        return $this;
    }

    /**
     * @param string $fixturePath
     * @param string $fixtureKey
     * @return $this
     */
    public function setFixture($fixturePath, $fixtureKey = null)
    {
        $this->fixturePath = $fixturePath;
        $this->fixtureKey  = $fixtureKey;

        return $this;
    }

    /**
     * @param FormTypeInterface $formType
     * @return $this
     */
    public function setFormType(FormTypeInterface $formType)
    {
        $this->formType = $formType;

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
    public function determineValues()
    {
        $this->checkVariables();

        $handler = $this->determinerHandler;
        $fields        = $handler->getFields($this->modelClass);
        $formElements  = $handler->getFormElements($this->modelClass, $this->formType);
        $fixtureValues = $handler->getFixtureValues($this->modelClass, $this->fixturePath, $this->fixtureKey);

        $fieldsForTest = [];
        foreach ($formElements as $fieldName => $formTypeName) {
            if ($formTypeName == 'file') {
                continue;
            }

            $fieldType = null;
            if (isset($fields[$fieldName])) {
                $fieldType = $fields[$fieldName];

            } elseif (in_array($fieldType, ['integer', 'text', 'string', 'textarea'])) {
                $fieldType = ValueUtil::getFieldTypeByFormFype($formTypeName);
            }

            if (!$fieldType) {
                continue;
            }

            if (isset($fixtureValues[$fieldName])) {
                $fixtureValue = $fixtureValues[$fieldName];
                $value = ValueUtil::modifyValue($fixtureValue, $fieldType, 'new');

            } else {
                $value = ValueUtil::newValue($fieldType, $fieldName);
            }

            $fieldsForTest[$fieldName] = $value;
        }

        return $fieldsForTest;
    }

    /**
     * @return array
     */
    public function determineFiles()
    {
        $handler = $this->determinerHandler;
        $formElements  = $handler->getFormElements($this->modelClass, $this->formType);

        $filesForTest = [];
        foreach ($formElements as $fieldName => $formType) {
            if ($formType != 'file') {
                continue;
            }

            $filesForTest[$fieldName] = $this->fileFaker->getFakeUploadedImage();
        }

        return $filesForTest;
   }

    /**
     * @param array $values
     * @return array
     */
    public function determineExpected(array $values)
    {
        $handler = $this->determinerHandler;
        $fields           = $handler->getFields($this->modelClass);
        $associations     = $handler->getAssociations($this->modelClass);
        $serializerGroups = $handler->getSerializerGroups($this->modelClass);

        $expected = [];
        foreach ($values as $fieldName => $value) {
            if ($this->viewScope &&
                (!isset($serializerGroups[$fieldName]) || !in_array($this->viewScope, $serializerGroups[$fieldName]))
            ) {
                continue;
            }

            if (isset($associations[$fieldName])) {
                $expected[$fieldName] = ['id' => $values[$fieldName]];

            } else {
                $fieldType = $fields[$fieldName];
                if ($fieldType == 'date' or $fieldType == 'datetime') {
                    $dateTime = new \DateTime($value);
                    $expected[$fieldName] = $dateTime->format('c');

                } else {
                    $expected[$fieldName] = $value;
                }
            }
        }

        return $expected;
    }

    /**
     * Check variables
     *
     * @throws \RuntimeException
     */
    protected function checkVariables()
    {
        if (!$this->modelClass) {
            throw new \RuntimeException('Variable {@modelClass} not define.');
        }

        if (!$this->fixturePath) {
            throw new \RuntimeException('Variable {@fixturePath} not define.');
        }

        if (!$this->formType) {
            throw new \RuntimeException('Variable {@formType} not define.');
        }
    }
}