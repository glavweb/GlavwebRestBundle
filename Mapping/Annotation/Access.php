<?php

namespace Glavweb\RestBundle\Mapping\Annotation;

/**
 * Class Access
 * @package Glavweb\RestBundle\Mapping\Annotation
 *
 * @Annotation
 * @Target({"CLASS"})
 */
class Access
{
    /**
     * @var string
     */
    public $baseRole;

    /**
     * @var string
     */
    public $name;

    /**
     * @var array
     */
    public $additionalRoles = [];

    /**
     * @return string
     */
    public function getBaseRole()
    {
        return $this->baseRole;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return array
     */
    public function getAdditionalRoles()
    {
        return $this->additionalRoles;
    }
}

