<?php

/*
 * This file is part of the Glavweb RestBundle package.
 *
 * (c) Andrey Nilov <nilov@glavweb.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Glavweb\RestBundle\Mapping\Annotation;

/**
 * Class Scope
 *
 * @author Nilov Andrey <nilov@glavweb.ru>
 * @package Glavweb\RestBundle
 * @Annotation
 * @Target("METHOD")
 */
class Scope
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $path;

    /**
     * @var string
     */
    public $role;

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @return string
     */
    public function getRole()
    {
        return $this->role;
    }
}

