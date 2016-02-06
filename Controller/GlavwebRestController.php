<?php

namespace Glavweb\RestBundle\Controller;

use Doctrine\ORM\EntityRepository;
use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Request\ParamFetcherInterface;
use FOS\RestBundle\View\View;
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

        if ($limit > 0 && $total > 0 && $offset < $total) {
            $end = $offset + $limit;
            $end = $end > $total ? $total : $end;

            if ($end < $total) {
                $view->setStatusCode(Response::HTTP_PARTIAL_CONTENT);
                $view->setHeader('Content-Range', "items $offset-$end/$total");
            }
        }
    }

    /**
     * @param Request $request
     * @param string $paramName
     * @return array
     */
    protected function getScopesByRequest(Request $request, $paramName = '_scope')
    {
        $scopes = array_map('trim', explode(',', $request->get($paramName)));
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
     * @param DoctrineMatcherResult $matcherResult
     * @param SerializationContext $serializationContext
     * @return \FOS\RestBundle\View\View
     */
    protected function createViewByMatcher(DoctrineMatcherResult $matcherResult, SerializationContext $serializationContext, $statusCode = null, array $headers = array())
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
