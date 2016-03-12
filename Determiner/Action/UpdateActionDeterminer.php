<?php

namespace Glavweb\RestBundle\Determiner\Action;

use Glavweb\RestBundle\Determiner\DeterminerHandler;
use Glavweb\RestBundle\Determiner\ValueUtil;
use Symfony\Component\Form\FormTypeInterface;

/**
 * Class UpdateActionDeterminer
 * @package Glavweb\RestBundle\Determiner\Action
 */
class UpdateActionDeterminer
{
    /**
     * @var DeterminerHandler
     */
    protected $determinerHandler;

    /**
     * @var object
     */
    protected $model;

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
    protected $viewScope;

    /**
     * PostActionDeterminer constructor.
     *
     * @param DeterminerHandler $determinerHandler
     */
    public function __construct(DeterminerHandler $determinerHandler)
    {
        $this->determinerHandler = $determinerHandler;
    }

    /**
     * @param object $model
     * @return $this
     */
    public function setModel($model)
    {
        $this->model      = $model;
        $this->modelClass = get_class($model);

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
        $handler = $this->determinerHandler;
        $fields        = $handler->getFields($this->modelClass);
        $formElements  = $handler->getFormElements($this->modelClass, $this->formType);

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

            $getter = 'get' . ucfirst($fieldName);
            if (method_exists($this->model, $getter)) {
                $value = ValueUtil::modifyValue($this->model->$getter(), $fieldType, 'update');

            } else {
                $value = ValueUtil::newValue($fieldType, $fieldName);
            }

            $fieldsForTest[$fieldName] = $value;
        }

        return $fieldsForTest;
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
            $isViewScope =
                $this->viewScope &&
                isset($serializerGroups[$fieldName]) &&
                in_array($this->viewScope, $serializerGroups[$fieldName])
            ;

            if (!$isViewScope) {
                continue;
            }

            if (isset($associations[$fieldName])) {
                $expected[$fieldName] = ['id' => $values[$fieldName]];

            } else {
                $fieldType = $fields[$fieldName];
                if ($fieldType == 'date' or $fieldType == 'datetime') {
                    $dateTime = $value instanceof \DateTime ? $value : new \DateTime($value);
                    $expected[$fieldName] = $dateTime->format('c');

                } else {
                    $expected[$fieldName] = $value;
                }
            }
        }

        return $expected;
    }
}