<?php

namespace Glavweb\RestBundle\Test\Authenticate;

/**
 * Interface AuthenticatorInterface
 * @package Glavweb\RestBundle\Authenticate
 */
interface AuthenticatorInterface
{
    /**
     * @return AuthenticateResponse
     */
    public function authenticate();

    /**
     * @return string
     */
    public function getCacheKey();
}