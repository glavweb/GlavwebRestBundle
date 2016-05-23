<?php


namespace Glavweb\RestBundle\EventListener;

use Glavweb\RestBundle\Scope\ScopeFetcherInterface;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;

class ScopeFetcherListener
{
    /**
     * Name of scope fetcher interface
     */
    const SCOPE_FETCHER_INTERFACE = 'Glavweb\\RestBundle\\Scope\\ScopeFetcherInterface';

    /**
     * @var ScopeFetcherInterface
     */
    private $scopeFetcher;

    /**
     * Constructor.
     * 
     * @param ScopeFetcherInterface $scopeFetcher
     */
    public function __construct(ScopeFetcherInterface $scopeFetcher)
    {
        $this->scopeFetcher = $scopeFetcher;
    }

    /**
     * Core controller handler.
     *
     * @param FilterControllerEvent $event
     *
     * @throws \InvalidArgumentException
     */
    public function onKernelController(FilterControllerEvent $event)
    {
        $request    = $event->getRequest();
        $controller = $event->getController();

        if (is_callable($controller) && method_exists($controller, '__invoke')) {
            $controller = [$controller, '__invoke'];
        }
        
        $this->scopeFetcher->setController($controller);

        $attributeName = $this->getAttributeName($controller);
        $request->attributes->set($attributeName, $this->scopeFetcher);
    }

    /**
     * Determines which attribute the ParamFetcher should be injected as.
     *
     * @param array $controller The controller action as an "array" callable.
     *
     * @return string
     */
    private function getAttributeName(array $controller)
    {
        list($controllerObject, $methodName) = $controller;

        $method = new \ReflectionMethod($controllerObject, $methodName);
        foreach ($method->getParameters() as $param) {
            if ($this->isScopeFetcherType($param)) {
                return $param->getName();
            }
        }

        // If there is no typehint, inject the ScopeFetcher using a default name.
        return 'scopeFetcher';
    }

    /**
     * Returns true if the given controller parameter is type-hinted as an instance of ScopeFetcher.
     *
     * @param \ReflectionParameter $controllerParam A parameter of the controller action.
     *
     * @return bool
     */
    private function isScopeFetcherType(\ReflectionParameter $controllerParam)
    {
        $class = $controllerParam->getClass();
        
        if (!$class) {
            return false;
        }

        return $class->implementsInterface(self::SCOPE_FETCHER_INTERFACE);
    }
}
