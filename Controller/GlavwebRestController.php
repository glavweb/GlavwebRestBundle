<?php

namespace Glavweb\RestBundle\Controller;

use Doctrine\ORM\EntityRepository;
use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\View\View;
use Glavweb\DatagridBundle\Datagrid\DatagridInterface;
use Glavweb\DatagridBundle\JoinMap\Doctrine\JoinMap;
use Glavweb\DataSchemaBundle\DataSchema\DataSchema;
use Glavweb\DataSchemaBundle\Loader\Yaml\DataSchemaYamlLoader;
use Glavweb\DataSchemaBundle\Loader\Yaml\ScopeYamlLoader;
use Glavweb\RestBundle\Serializer\ScopeExclusionStrategy;
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
        $total  = (int)$total;

        $needRange = $limit >= 0 || $offset >= 0;
        if ($needRange) {
            if ($limit === null) {
                $end = $total - 1;

            } else {
                $end = $offset + $limit - 1;
                $end = min($end, $total - 1);
            }

            if ($limit !== null && (int)$limit === 0) {
                $offset = '';
                $end    = '';
            }

            $view->setHeader('Content-Range', "items $offset-$end/$total");
        }

        $needPartialContentStatus = $offset > 0 || ($limit !== null && $total > $limit);
        if ($needPartialContentStatus) {
            $view->setStatusCode(Response::HTTP_PARTIAL_CONTENT);
        }
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
     * @param DatagridInterface $datagrid
     * @param string            $statusCode
     * @param array             $headers
     * @return View
     */
    protected function createListViewByDatagrid(DatagridInterface $datagrid, $statusCode = null, array $headers = array())
    {
        $offset = $datagrid->getFirstResult();
        $limit  = $datagrid->getMaxResults();

        $view = $this->view($datagrid->getList(), $statusCode, $headers);
        $this->setContentRangeHeader($view, $offset, $limit, $datagrid->getTotal());

        return $view;
    }
}
