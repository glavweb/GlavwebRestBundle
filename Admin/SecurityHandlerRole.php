<?php

namespace Glavweb\RestBundle\Admin;

use Glavweb\RestBundle\Security\AccessHandler;
use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Security\Handler\SecurityHandlerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;

/**
 * Class SecurityHandlerRole
 * @package Glavweb\RestBundle\Admin
 */
class SecurityHandlerRole implements SecurityHandlerInterface
{
    /**
     * @var AuthorizationCheckerInterface
     */
    protected $authorizationChecker;

    /**
     * @var AccessHandler
     */
    private $accessHandler;

    /**
     * @var array
     */
    protected $superAdminRoles;

    /**
     * @var array
     */
    protected $roleReplaces = [
        'LIST'   => 'LIST',
        'VIEW'   => 'VIEW',
        'CREATE' => 'CREATE',
        'EDIT'   => 'EDIT',
        'DELETE' => 'DELETE',
        'EXPORT' => 'EXPORT',
    ];

    /**
     * @param AuthorizationCheckerInterface $authorizationChecker
     * @param AccessHandler $accessHandler
     * @param array $superAdminRoles
     */
    public function __construct(AuthorizationCheckerInterface $authorizationChecker, AccessHandler $accessHandler, array $superAdminRoles)
    {
        $this->authorizationChecker = $authorizationChecker;
        $this->superAdminRoles = $superAdminRoles;
        $this->accessHandler   = $accessHandler;
    }

    /**
     * {@inheritdoc}
     */
    public function isGranted(AdminInterface $admin, $attributes, $object = null)
    {
        if (!is_array($attributes)) {
            $attributes = array($attributes);
        }

        foreach ($attributes as $pos => $attribute) {
            $attribute = isset($this->roleReplaces[$attribute]) ? $this->roleReplaces[$attribute] : $attribute;
            $attributes[$pos] = sprintf($this->getBaseRole($admin), $attribute);
        }

        try {
            return 
                $this->authorizationChecker->isGranted($this->superAdminRoles) ||
                $this->authorizationChecker->isGranted($attributes, $object)
            ;
            
        } catch (AuthenticationCredentialsNotFoundException $e) {
            return false;
            
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getBaseRole(AdminInterface $admin)
    {
        $baseRole = $this->accessHandler->getBaseRole($admin->getClass());

        if (!$baseRole) {
            $baseRole = 'ROLE_' . str_replace('.', '_', strtoupper($admin->getCode())) . '_%s';
        }

        return $baseRole;
    }

    /**
     * {@inheritdoc}
     */
    public function buildSecurityInformation(AdminInterface $admin)
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function createObjectSecurity(AdminInterface $admin, $object)
    {}

    /**
     * {@inheritdoc}
     */
    public function deleteObjectSecurity(AdminInterface $admin, $object)
    {}
}