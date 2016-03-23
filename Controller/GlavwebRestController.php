<?php

namespace Glavweb\RestBundle\Controller;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\ResultSetMapping;
use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Request\ParamFetcherInterface;
use FOS\RestBundle\View\View;
use Glavweb\RestBundle\Service\AbstractDoctrineMatcherResult;
use Glavweb\RestBundle\Service\DoctrineMatcherResult;
use JMS\Serializer\Exclusion\GroupsExclusionStrategy;
use JMS\Serializer\SerializationContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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
        $scopes = array_merge($scopes, [GroupsExclusionStrategy::DEFAULT_GROUP]);

        return $scopes;
    }

    /**
     * @param Request $request
     * @param bool $enableMaxDepthChecks
     * @return SerializationContext
     */
    protected function getSerializationContext(Request $request, $enableMaxDepthChecks = true)
    {
        $scopes = $this->getScopesByRequest($request);

        $serializationContext = SerializationContext::create()
            ->setGroups(array_merge($scopes, [GroupsExclusionStrategy::DEFAULT_GROUP]))
        ;

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
