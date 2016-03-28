<?php

namespace Glavweb\RestBundle\Security;

use Doctrine\Common\Annotations\Reader;
use Glavweb\RestBundle\Mapping\Annotation\Access;

/**
 * Class AccessHandler
 * @package Glavweb\RestBundle\Security
 */
class AccessHandler
{
    /**
     * @var array
     */
    private static $accessAnnotationCache = [];

    /**
     * @var array
     */
    private $actions = ['CREATE', 'LIST', 'VIEW', 'EDIT', 'DELETE', 'EXPORT'];

    /**
     * @var Reader
     */
    protected $annotationReader;

    /**
     * AccessHandler constructor.
     * @param Reader $annotationReader
     */
    public function __construct(Reader $annotationReader)
    {
        $this->annotationReader = $annotationReader;
    }

    /**
     * @param bool $onlyForObjects
     * @return array
     */
    public function getActions($onlyForObjects = false)
    {
        $actions = $this->actions;

        if ($onlyForObjects) {
            $actions = array_filter($actions, function ($item) {
                return !in_array($item, ['CREATE', 'LIST']);
            });
        }

        return $actions;
    }

    /**
     * @param string $class
     * @return array|null
     */
    public function getAdditionalRoles($class)
    {
        $accessAnnotation = $this->getAccessAnnotation($class);
        if ($accessAnnotation instanceof Access) {
            return $accessAnnotation->getAdditionalRoles();
        }

        return null;
    }

    /**
     * @param string $class
     * @return string|null
     */
    public function getBaseRole($class)
    {
        $accessAnnotation = $this->getAccessAnnotation($class);
        if ($accessAnnotation instanceof Access) {
            return $accessAnnotation->getBaseRole();
        }

        return null;
    }

    /**
     * @param string $class
     * @param string $role
     * @return bool
     */
    public function checkRole($class, $role)
    {
        return (bool)$this->getActionByRole($class, $role);
    }

    /**
     * @param string $class
     * @param string $role
     * @return string|null
     */
    public function getActionByRole($class, $role)
    {
        $baseRole = $this->getBaseRole($class);

        if (!$baseRole) {
            return false;
        }

        foreach ($this->actions as $action) {
            if ($this->makeRole($baseRole, $action) == $role) {
                return $action;
            }
        }

        return null;
    }

    /**
     * @param string $class
     * @param string $action
     * @param string $additionalRole
     * @return null|string
     */
    public function getRole($class, $action, $additionalRole = null)
    {
        $baseRole = $this->getBaseRole($class);

        if (!$baseRole) {
            return null;
        }

        return $this->makeRole($baseRole, strtoupper($action), $additionalRole);
    }

    /**
     * @param string $baseRole
     * @param string $action
     * @param string $additionalRole
     * @return null|string
     */
    protected function makeRole($baseRole, $action, $additionalRole = null)
    {
        $role = sprintf($baseRole, strtoupper($action));

        if ($additionalRole) {
            $role .= '__' . strtoupper($additionalRole);
        }

        return $role;
    }

    /**
     * @param string $class
     * @return bool
     */
    public function hasAccessAnnotation($class)
    {
        return (bool)$this->getAccessAnnotation($class);
    }

    /**
     * @param string|\ReflectionClass $class
     * @return Access|null
     */
    public function getAccessAnnotation($class)
    {
        $className = $class;
        if ($class instanceof \ReflectionClass) {
            $className = $class->getName();
        }

        if (!isset(self::$accessAnnotationCache[$className])) {
            $reflectionClass = $class;
            if (!$reflectionClass instanceof \ReflectionClass) {
                $reflectionClass = new \ReflectionClass($reflectionClass);
            }

            self::$accessAnnotationCache[$className] = $this->annotationReader->getClassAnnotation(
                $reflectionClass,
                'Glavweb\RestBundle\Mapping\Annotation\Access'
            );
        }

        return self::$accessAnnotationCache[$className];
    }
}