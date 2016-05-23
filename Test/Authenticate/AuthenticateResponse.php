<?php

namespace Glavweb\RestBundle\Test\Authenticate;

/**
 * Class AuthenticateResponse
 * @package Glavweb\RestBundle\Authenticate
 */
class AuthenticateResponse
{
    /**
     * @var array
     */
    private $parameters;

    /**
     * @var array
     */
    private $headers;

    /**
     * AuthenticateResponse constructor.
     *
     * @param array $parameters
     * @param array $headers
     */
    public function __construct(array $parameters = [], array $headers = [])
    {
        $this->parameters = $parameters;
        $this->headers = $headers;
    }

    /**
     * @return array
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * @param array $parameters
     */
    public function setParameters($parameters)
    {
        $this->parameters = $parameters;
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @param array $headers
     */
    public function setHeaders($headers)
    {
        $this->headers = $headers;
    }
}