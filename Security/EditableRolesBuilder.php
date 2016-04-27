<?php

namespace Glavweb\RestBundle\Security;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\Mapping\ClassMetadata;

class EditableRolesBuilder
{
    /**
     * @var
     */
    protected $doctrine;

    /**
     * @var AccessHandler
     */
    protected $accessHandler;

    /**
     * @var array
     */
    protected $rolesHierarchy;

    /**
     * @param Registry $doctrine
     * @param AccessHandler $accessHandler
     * @param array $rolesHierarchy
     * @internal param Reader $annotationReader
     */
    public function __construct(Registry $doctrine, AccessHandler $accessHandler, array $rolesHierarchy = [])
    {
        $this->doctrine        = $doctrine;
        $this->accessHandler   = $accessHandler;
        $this->rolesHierarchy  = $rolesHierarchy;
    }

    /**
     * @return array
     */
    public function getRoles()
    {
        /** @var ClassMetadata[] $allMetaData */
        $actions            = $this->accessHandler->getActions();
        $actionsOnlyObjects = $this->accessHandler->getActions(true);
        $em                 = $this->doctrine->getManager();

        // Entity roles
        $entityRoles = array();
        $allMetaData = $em->getMetadataFactory()->getAllMetadata();
        foreach ($allMetaData as $metaData) {
            $reflectionClass = $metaData->getReflectionClass();
            $accessAnnotation = $this->accessHandler->getAccessAnnotation($reflectionClass);

            if (!$accessAnnotation) {
                continue;
            }

            $entityClass = $metaData->getName();
            $entityName  = $accessAnnotation->getName() ?: current(array_slice(explode('\\', $entityClass), -1));

            // Master
            $entityRoles[$entityName]['master']['name'] = 'Master';
            foreach ($actions as $action) {
                $role = $this->accessHandler->getRole($reflectionClass, $action);
                $entityRoles[$entityName]['master']['roles'][$role] = $role;
            }

            // Additional Roles
            $additionalRoles = $accessAnnotation->getAdditionalRoles();
            foreach ($additionalRoles as $additionalRoleName => $additionalRoleData) {
                $roleTitle = isset($additionalRoleData['name']) ? $additionalRoleData['name'] : ucfirst($additionalRoleName);
                $entityRoles[$entityName][$additionalRoleName]['name'] = $roleTitle;

                foreach ($actionsOnlyObjects as $action) {
                    $role = $this->accessHandler->getRole($reflectionClass, $action, $additionalRoleName);
                    $entityRoles[$entityName][$additionalRoleName]['roles'][$role] = $role;
                }
            }
        }

        // Get roles from the service container
        $securityRoles = [];
        foreach ($this->rolesHierarchy as $name => $rolesHierarchy) {
            $securityRoles[$name] = $name . ': ' . implode(', ', $rolesHierarchy);
            foreach ($rolesHierarchy as $action) {
                if (!isset($securityRoles[$action])) {
                    $securityRoles[$action] = $action;
                }
            }
        }
        
        return [$entityRoles, $securityRoles];
    }
}