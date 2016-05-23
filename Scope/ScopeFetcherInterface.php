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

/**
 * Interface ScopeFetcherInterface
 *
 * @author Nilov Andrey <nilov@glavweb.ru>
 * @package Glavweb\RestBundle
 */
interface ScopeFetcherInterface
{
    /**
     * Sets the controller.
     *
     * @param callable $controller
     * @return void
     */
    public function setController($controller);

    /**
     * Returns first available scope path by name
     *
     * @param string $requestName
     * @param string $default
     * @return string|null
     */
    public function getAvailable($requestName = null, $default = null);

    /**
     * Returns scope path by name
     *
     * @param string $name
     * @param bool $checkAccess
     * @param null $default
     * @return null|string
     */
    public function get($name, $checkAccess = true, $default = null);

    /**
     * @param string $name
     * @return bool
     */
    public function has($name);

    /**
     * Check role defined in scope annotation
     *
     * @param string $name
     * @return bool
     */
    public function isGranted($name);

}
