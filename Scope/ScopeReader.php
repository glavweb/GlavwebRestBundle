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

use Doctrine\Common\Annotations\Reader;
use Glavweb\RestBundle\Mapping\Annotation\Scope;

/**
 * Class ScopeReader
 *
 * @author Nilov Andrey <nilov@glavweb.ru>
 * @package Glavweb\RestBundle
 */
class ScopeReader
{
    /**
     * @var Reader
     */
    private $annotationReader;

    /**
     * Initializes scope reader.
     *
     * @param Reader $annotationReader
     */
    public function __construct(Reader $annotationReader)
    {
        $this->annotationReader = $annotationReader;
    }

    /**
     * @param \ReflectionClass $reflection
     * @param string $method
     * @return array
     */
    public function read(\ReflectionClass $reflection, $method)
    {
        if (!$reflection->hasMethod($method)) {
            throw new \InvalidArgumentException(sprintf("Class '%s' has no method '%s' method.", $reflection->getName(), $method));
        }

        $scopeAnnotations = $this->getScopeAnnotationsFromMethod($reflection->getMethod($method));

        return $scopeAnnotations;
    }

    /**
     * @param \ReflectionMethod $method
     * @return Scope[]
     */
    public function getScopeAnnotationsFromMethod(\ReflectionMethod $method)
    {
        $scopeAnnotations = [];

        $methodAnnotations = $this->annotationReader->getMethodAnnotations($method);
        foreach ($methodAnnotations as $annotation) {
            if ($annotation instanceof Scope) {
                $scopeAnnotations[$annotation->getName()] = $annotation;
            }
        }

        return $scopeAnnotations;
    }
}
