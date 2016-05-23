<?php

namespace Glavweb\RestBundle\Test\Guesser\Action;
use Glavweb\RestBundle\Test\Guesser\GuesserHandler;
use Glavweb\RestBundle\Test\Guesser\ValueUtil;
use Symfony\Component\Form\FormTypeInterface;

/**
 * Class PatchActionGuesser
 * @package Glavweb\RestBundle\Test\Guesser\Action
 */
class PatchActionGuesser
{
    /**
     * @var GuesserHandler
     */
    protected $guesserHandler;

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
     * PostActionGuesser constructor.
     *
     * @param GuesserHandler $guesserHandler
     */
    public function __construct(GuesserHandler $guesserHandler)
    {
        $this->guesserHandler = $guesserHandler;
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
    public function guessCases()
    {
        $handler = $this->guesserHandler;
        $formElements  = $handler->getFormElements($this->modelClass, $this->formType);

        $caseForTest = [];
        foreach ($formElements as $fieldName => $formTypeName) {
            if ($formTypeName == 'file') {
                continue;
            }

            $fieldType = $this->getFieldType($fieldName);
            if (!$fieldType) {
                continue;
            }

            $getter = 'get' . ucfirst($fieldName);
            if (method_exists($this->model, $getter)) {
                $value = ValueUtil::modifyValue($this->model->$getter(), $fieldType, 'update');

            } else {
                $value = ValueUtil::newValue($fieldType, $fieldName);
            }

            $expectedValue = $this->getExpectedValue($fieldName, $fieldType, $value);
            if (!$expectedValue) {
                continue;
            }

            $caseForTest[$fieldName] = [
                'values'   => [$fieldName => $value],
                'expected' => [$fieldName => $expectedValue]
            ];
        }

        return $caseForTest;
    }

    /**
     * @param string $fieldName
     * @param string $fieldType
     * @param mixed $value
     * @return array|string
     */
    private function getExpectedValue($fieldName, $fieldType, $value)
    {
        $handler = $this->guesserHandler;
        $associations     = $handler->getAssociations($this->modelClass);
        $serializerGroups = $handler->getSerializerGroups($this->modelClass);

        $isViewScope =
            $this->viewScope &&
            isset($serializerGroups[$fieldName]) && 
            in_array($this->viewScope, $serializerGroups[$fieldName])
        ;

        $expectedValue = null;
        if ($isViewScope) {
            if (isset($associations[$fieldName])) {
                $expectedValue = ['id' => $value];
    
            } elseif ($fieldType == 'date' or $fieldType == 'datetime') {
                $dateTime = $value instanceof \DateTime ? $value : new \DateTime($value);
                $expectedValue = $dateTime->format('c');
    
            } else {
                $expectedValue = $value;
            }
        }

        return $expectedValue;
    }
    
    /**
     * @param string $fieldName
     * @return string|null
     */
    private function getFieldType($fieldName)
    {
        $handler = $this->guesserHandler;
        $fields        = $handler->getFields($this->modelClass);
        $formElements  = $handler->getFormElements($this->modelClass, $this->formType);

        $fieldType = null;
        if (isset($fields[$fieldName])) {
            $fieldType = $fields[$fieldName];

        } elseif (isset($formElements[$fieldName]) && in_array($fieldType, ['integer', 'text', 'string', 'textarea'])) {
            $formTypeName = $formElements[$fieldName];
            $fieldType = ValueUtil::getFieldTypeByFormFype($formTypeName);
        }

        return $fieldType;
    }
}