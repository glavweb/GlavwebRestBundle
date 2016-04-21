<?php

namespace Glavweb\RestBundle\Controller;

use Doctrine\ORM\EntityRepository;
use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\View\View;
use Glavweb\DatagridBundle\Datagrid\DatagridInterface;
use Glavweb\DatagridBundle\JoinMap\Doctrine\JoinMap;
use Glavweb\RestBundle\Scope\ScopeExclusionStrategy;
use Glavweb\RestBundle\Scope\ScopeYamlLoader;
use Glavweb\RestBundle\Service\AbstractDoctrineMatcherResult;
use JMS\Serializer\Exclusion\GroupsExclusionStrategy;
use JMS\Serializer\SerializationContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Config\FileLocator;

/**
 * Class GlavwebRestController
 * @package Glavweb\RestBundle\Controller
 */
class GlavwebRestController extends FOSRestController
{
    /**
     * @param Request $request
     * @param string  $key
     * @return mixed
     */
    protected function getRestParam(Request $request, $key)
    {
        if (in_array($request->getMethod(), ['PUT', 'PATCH', 'POST'])) {
            $default = $request->get($key);
            $actual  = $request->request->get($key);

            if ($actual === null) {
                return $default;
            }

            return $actual;
        }

        return $request->get($key);
    }

    /**
     * @param View $view
     * @param int $offset
     * @param int $limit
     * @param int $total
     */
    protected function setContentRangeHeader(View $view, $offset, $limit, $total)
    {
        $offset = (int)$offset;
        $limit  = (int)$limit;
        $total  = (int)$total;

        if ($limit > 0 && $total > 0 && $offset < $total && $total > $limit) {
            $end = $offset + $limit;
            $end = $end > $total ? $total : $end;
            
            $view->setStatusCode(Response::HTTP_PARTIAL_CONTENT);
            $view->setHeader('Content-Range', "items $offset-$end/$total");
        }
    }

    /**
     * @param Request $request
     * @param string $paramName
     * @return array
     */
    protected function getScopesByRequest(Request $request, $paramName = '_scope')
    {
        $scopes = array_map('trim', explode(',', $this->getRestParam($request, $paramName)));

        return $scopes;
    }

    /**
     * @param Request $request
     * @return array
     */
    protected function getScopeConfig(Request $request)
    {
        $scopes = $this->getScopesByRequest($request);

        $locationDir = $this->getParameter('kernel.root_dir') . '/config/scopes';
        $scopeLoader = new ScopeYamlLoader(new FileLocator($locationDir));

        foreach ($scopes as $scope) {
            $scopeLoader->load($scope . '.yml');
        }

        return $scopeLoader->getConfiguration();
    }

    /**
     * @param array $scopeConfig
     * @param bool  $enableMaxDepthChecks
     * @return SerializationContext
     */
    protected function getScopeSerializationContext(array $scopeConfig, $enableMaxDepthChecks = false)
    {
        $serializationContext = SerializationContext::create();
        $serializationContext->addExclusionStrategy(new ScopeExclusionStrategy($scopeConfig));

        if ($enableMaxDepthChecks) {
            $serializationContext->enableMaxDepthChecks();
        }

        return $serializationContext;
    }

    /**
     * @param AbstractDoctrineMatcherResult $matcherResult
     * @param SerializationContext          $serializationContext
     * @param string                        $statusCode
     * @param array                         $headers
     * @return View
     */
    protected function createViewByMatcher(AbstractDoctrineMatcherResult $matcherResult, SerializationContext $serializationContext, $statusCode = null, array $headers = array())
    {
        $offset = $matcherResult->getFirstResult();
        $limit  = $matcherResult->getMaxResults();

        $view = $this->view($matcherResult->getList(), $statusCode, $headers);
        $this->setContentRangeHeader($view, $offset, $limit, $matcherResult->getTotal());

        return $view
            ->setSerializationContext($serializationContext)
        ;
    }

    /**
     * @param AbstractDoctrineMatcherResult $datagrid
     * @param SerializationContext          $serializationContext
     * @param string                        $statusCode
     * @param array                         $headers
     * @return View
     */
    protected function createViewByDatagrid(DatagridInterface $datagrid, SerializationContext $serializationContext, $statusCode = null, array $headers = array())
    {
        $offset = $datagrid->getFirstResult();
        $limit  = $datagrid->getMaxResults();

        $view = $this->view($datagrid->getList(), $statusCode, $headers);
        $this->setContentRangeHeader($view, $offset, $limit, $datagrid->getTotal());

        return $view
            ->setSerializationContext($serializationContext)
        ;
    }

    /**
     * @param string $class
     * @return EntityRepository
     */
    protected function getRepository($class)
    {
        $repository = $this->getDoctrine()->getManager()->getRepository($class);

        if ($repository instanceof EntityRepository) {
            return $repository;
        }

        throw new \RuntimeException('Repository class must be instance of EntityRepository.');
    }

    /**
     * @param array $scopeConfig
     * @param string $alias
     * @return JoinMap
     */
    protected function createJoinMapByScopeConfig(array $scopeConfig, $alias)
    {
        $joinMap = new JoinMap($alias);

        $joins = $this->getJoinsByScopeConfig($scopeConfig, $alias);
        foreach ($joins as $fullPath => $joinData) {
            $pathElements = explode('.', $fullPath);
            $field = array_pop($pathElements);
            $path  = implode('.', $pathElements);

            if (($key = array_search($path, $joins)) !== false) {
                $path = $key;
            }

            $joinFields = $joinData['fields'];
            $joinMap->join($path, $field, true, $joinFields);
        }

        return $joinMap;
    }

    /**
     * @param array $scopeConfig
     * @param string $firstAlias
     * @param string $alias
     * @param array $result
     * @return array
     */
    protected function getJoinsByScopeConfig(array $scopeConfig, $firstAlias, $alias = null, &$result = [])
    {
        if (!$alias) {
            $alias = $firstAlias;
        }

        foreach ($scopeConfig as $key => $value) {
            if (is_array($value)) {
                $join       = $alias . '.' . $key;
                $joinAlias  = str_replace('.', '_', $join);
                $joinFields = array_filter($value, function ($value) {
                    return !is_array($value);
                });
                $joinFields = array_keys($joinFields);
                
                $result[$join] = [
                    'alias'  => $joinAlias,
                    'fields' => $joinFields
                ];

                $alias = $joinAlias;
                $this->getJoinsByScopeConfig($value, $firstAlias, $alias, $result);
                $alias = $firstAlias;
            }
        }

        return $result;
    }
}
