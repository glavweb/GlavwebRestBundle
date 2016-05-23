<?php

/*
 * This file is part of the Glavweb RestBundle package.
 *
 * (c) Andrey Nilov <nilov@glavweb.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Glavweb\RestBundle\Scope;

use Doctrine\Common\Util\ClassUtils;
use Glavweb\RestBundle\Mapping\Annotation\Scope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;

/**
 * Class ScopeFetcher
 *
 * @author Nilov Andrey <nilov@glavweb.ru>
 * @package Glavweb\RestBundle
 */
class ScopeFetcher implements ScopeFetcherInterface
{
    /**
     * @var ScopeReader
     */
    private $scopeReader;

    /**
     * @var AuthorizationChecker
     */
    private $authorizationChecker;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var callable
     */
    private $controller;

    /**
     * @var Scope[]
     */
    private $scopeAnnotations;

    /**
     * Initializes scope fetcher.
     *
     * @param ScopeReader          $scopeReader
     * @param AuthorizationChecker $authorizationChecker
     * @param Request              $request
     */
    public function __construct(ScopeReader $scopeReader, AuthorizationChecker $authorizationChecker, RequestStack $requestStack)
    {
        $this->scopeReader          = $scopeReader;
        $this->authorizationChecker = $authorizationChecker;
        $this->request              = $requestStack->getCurrentRequest();
    }

    /**
     * {@inheritDoc}
     */
    public function setController($controller)
    {
        $this->controller = $controller;
    }

    /**
     * {@inheritDoc}
     */
    public function getAvailable($requestName = null, $default = null)
    {
        $annotations = $this->getScopeAnnotations();
        if ($annotations) {
            if (!$requestName) {
                return $this->getFirstAvailable($annotations, $default);
            }

            $isCheckName = true;
            $firstGrantedAnnotation = null;
            foreach ($annotations as $annotation) {
                if (!$firstGrantedAnnotation && $this->isGranted($annotation->getName())) {
                    $firstGrantedAnnotation = $annotation;
                }

                if ($isCheckName && $requestName != $annotation->getName()) {
                    continue;
                }

                if ($this->isGranted($annotation->getName())) {
                    return $annotation->getPath();
                }
                $isCheckName = false;

                continue;
            }

            if ($firstGrantedAnnotation) {
                $firstGrantedAnnotation->getPath();
            }
        }
        
        return $default;
    }

    /**
     * {@inheritDoc}
     */
    public function get($name, $checkAccess = true, $default = null)
    {
        $annotations = $this->getScopeAnnotations();

        if (!isset($annotations[$name])) {
            return $default;
        }

        $annotation = $annotations[$name];
        if ($checkAccess && $this->isGranted($name)) {
            return $annotation->getPath();
        }

        return $default;
    }

    /**
     * {@inheritDoc}
     */
    public function has($name)
    {
        $annotations = $this->getScopeAnnotations();

        return isset($annotations[$name]);
    }

    /**
     * {@inheritDoc}
     */
    public function isGranted($name)
    {
        $annotations = $this->getScopeAnnotations();

        if (!isset($annotations[$name])) {
            throw new \RuntimeException('Scope is not defined.');
        }

        $annotation = $annotations[$name];
        $role = $annotation->getRole();
        if ($role) {
            return $this->authorizationChecker->isGranted($role);
        }

        return true;
    }

    /**
     * @return Scope[]
     */
    private function getScopeAnnotations()
    {
        if (!$this->scopeAnnotations) {
            if (!$this->controller) {
                throw new \InvalidArgumentException('Controller and method needs to be set via setController');
            }
    
            if (!is_array($this->controller) || empty($this->controller[0]) || !is_object($this->controller[0])) {
                throw new \InvalidArgumentException(
                    'Controller needs to be set as a class instance (closures/functions are not supported)'
                );
            }
    
            $this->scopeAnnotations = $this->scopeReader->read(
                new \ReflectionClass(ClassUtils::getClass($this->controller[0])),
                $this->controller[1]
            );
        }

        return $this->scopeAnnotations;
    }

    /**
     * @param Scope[] $annotations
     * @param string  $default
     * @return string|null
     */
    private function getFirstAvailable($annotations, $default = null)
    {
        foreach ($annotations as $annotation) {
            if ($this->isGranted($annotation->getName())) {
                return $annotation->getPath();
            }
        }

        return $default;
    }
}
