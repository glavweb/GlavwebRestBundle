<?php

namespace Glavweb\RestBundle\Controller;

use Doctrine\ORM\EntityRepository;
use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\View\View;
use Glavweb\DatagridBundle\Datagrid\DatagridInterface;
use Glavweb\RestBundle\Scope\ScopeExclusionStrategy;
use Glavweb\RestBundle\Scope\ScopeYamlLoader;
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
     * @param DatagridInterface    $datagrid
     * @param SerializationContext $serializationContext
     * @param string               $statusCode
     * @param array                $headers
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
}
