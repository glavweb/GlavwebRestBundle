<?php

namespace Glavweb\RestBundle\Mapping\Annotation;

/**
 * Class Rest
 * @package Glavweb\RestBundle\Mapping\Annotation
 *
 * @Annotation
 * @Target({"CLASS"})
 */
class Rest
{
    /**
     * @var array
     */
    public $methods;

    /**
     * @var array
     */
    public $associations;

    /**
     * @var array
     */
    public $files;

    /**
     * @var array
     */
    public $enums;

    /**
     * @return array
     */
    public function getMethods()
    {
        return (array)$this->methods;
    }

    /**
     * @return array
     */
    public function getAssociations()
    {
        return (array)$this->associations;
    }

    /**
     * @return array
     */
    public function getFiles()
    {
        return (array)$this->files;
    }

    /**
     * @return array
     */
    public function getEnums()
    {
        return (array)$this->enums;
    }
}

