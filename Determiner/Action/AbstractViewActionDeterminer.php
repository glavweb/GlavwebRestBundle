<?php

namespace Glavweb\RestBundle\Determiner\Action;

use Glavweb\RestBundle\Scope\ScopeYamlLoader;
use Nelmio\ApiDocBundle\Extractor\ApiDocExtractor;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\Config\FileLocator;

/**
 * Class AbstractActionDeterminer
 * @package Glavweb\RestBundle\Determiner\Action
 */
abstract class AbstractViewActionDeterminer
{
    /**
     * @var Router
     */
    protected $router;

    /**
     * @var \Nelmio\ApiDocBundle\Annotation\ApiDoc
     */
    protected $apiDocAnnotation = null;

    /**
     * @var string
     */
    protected $scopeDir;

    /**
     * @var ApiDocExtractor
     */
    protected $apiDocExtractor;

    /**
     * @var object
     */
    protected $model;

    /**
     * @var string
     */
    protected $modelClass;

    /**
     * @var array
     */
    protected $skipValues = [];

    /**
     * @var string
     */
    protected $route;

    /**
     * @var array
     */
    protected $routeParameters = [];

    /**
     * @var array
     */
    protected $controllerInfo = null;

    /**
     * ViewActionDeterminer constructor.
     *
     * @param Router $router
     * @param ApiDocExtractor $apiDocExtractor
     * @param string $scopeDir
     */
    public function __construct(Router $router, ApiDocExtractor $apiDocExtractor, $scopeDir)
    {
        $this->router          = $router;
        $this->apiDocExtractor = $apiDocExtractor;
        $this->scopeDir        = $scopeDir;
    }

    /**
     * @param object $model
     * @return $this
     */
    public function setModel($model)
    {
        $this->model      = $model;
        $this->modelClass = get_class($model);

        return $this;
    }

    /**
     * @param string $route
     * @param array  $parameter
     * @return $this
     */
    public function setRoute($route, array $parameter = [])
    {
        $this->route          = $route;
        $this->routeParameters = $parameter;

        return $this;
    }

    /**
     * @param string $skipValues
     * @return $this
     */
    public function setSkipValues($skipValues)
    {
        $this->skipValues = $skipValues;

        return $this;
    }

    /**
     * @param string $scope
     * @return array
     */
    public function getScopeConfig($scope)
    {
        $scopeLoader = new ScopeYamlLoader(new FileLocator($this->scopeDir));
        $scopeLoader->load($scope . '.yml');

        $scopeConfig = $scopeLoader->getConfiguration();
        $scopeConfigPrepared = $this->skipValuesFromScopeConfig($scopeConfig, $this->skipValues);

        return $scopeConfigPrepared;
    }

    /**
     * @return array
     */
    public function getScopes()
    {
        $requirements = $this->getApiDocAnnotation()->getRequirements();
        if (!isset($requirements['_scope']['requirement'])) {
            throw new \RuntimeException('Scope requirement not found in api doc annotation.');
        }

        return explode('|', $requirements['_scope']['requirement']);
    }

    /**
     * @param array $queryParameters
     * @return string
     */
    public function getUri(array $queryParameters = [])
    {
        $url = $this->router->generate($this->route, $this->routeParameters);

        return $this->addQueryParameters($url, $queryParameters);
    }

    /**
     * @param array $scopeConfig
     * @param array $skipValues
     * @return array
     */
    protected function skipValuesFromScopeConfig(array $scopeConfig, array $skipValues)
    {
        if (!$skipValues) {
            return $scopeConfig;
        }

        $skipKeys = array_flip(array_filter($skipValues, function ($value) {
            return !is_array($value);
        }));

        // Remove skip values from scope config
        $intersectResult = array_intersect_key($scopeConfig, $skipKeys);
        if ($intersectResult) {
            $scopeConfig = array_diff_key($scopeConfig, $intersectResult);
        }

        foreach ($skipValues as $key => $value) {
            if (is_array($value)) {
                $scopeConfig[$key] = $this->skipValuesFromScopeConfig($scopeConfig[$key], $skipValues[$key]);
            }
        }

        return $scopeConfig;
    }

    /**
     * @param string $name
     * @return string
     */
    protected function getControllerInfo($name)
    {
        if ($this->controllerInfo === null) {
            $route = $this->router->getRouteCollection()->get($this->route);
            if (!$route) {
                throw new \RuntimeException(sprintf('Route %s not found.', $this->route));
            }

            $controller = $route->getDefault('_controller');
            list($controllerClass, $methodName) = explode('::', $controller);

            $this->controllerInfo = [
                'full'   => $controller,
                'class'  => $controllerClass,
                'method' => $methodName
            ];
        }

        if (!isset($this->controllerInfo[$name])) {
            throw new \RuntimeException(sprintf('Name %s not found in controller info.', $name));
        }

        return $this->controllerInfo[$name];
    }

    /**
     * @return \Nelmio\ApiDocBundle\Annotation\ApiDoc
     */
    protected function getApiDocAnnotation()
    {
        if (!$this->apiDocAnnotation) {
            $this->apiDocAnnotation = $this->apiDocExtractor->get($this->getControllerInfo('full'), $this->route);

            if (!$this->apiDocAnnotation) {
                throw new \RuntimeException('Api doc annotation not found.');
            }
        }

        return $this->apiDocAnnotation;
    }

    /**
     * @param $url
     * @param array $queryParameters
     * @return string
     */
    private function addQueryParameters($url, array $queryParameters)
    {
        if (!$queryParameters) {
            return $url;
        }

        $urlParts = parse_url($url);
        $query = isset($urlParts['query']) ? $urlParts['query'] : '';

        $queryData = [];
        if ($query) {
            parse_str($query, $queryData);
        }
        $query = http_build_query(array_merge($queryData, $queryParameters));

        return $urlParts['path'] . ($query ? '?' . $query : '');
    }
}