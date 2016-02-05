<?php

namespace Glavweb\RestBundle\Controller;

use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Request\ParamFetcherInterface;
use FOS\RestBundle\View\View;

/**
 * Class GlavwebRestController
 * @package Glavweb\RestBundle\Controller
 */
class GlavwebRestController extends FOSRestController
{
    /**
     * @param ParamFetcherInterface $paramFetcher
     * @return array
     */
    protected function getSystemParameters(ParamFetcherInterface $paramFetcher)
    {
        $scopes  = array_map('trim', explode(',', $paramFetcher->get('_scope')));
        $sort    = $paramFetcher->get('_sort');
        $limit   = $paramFetcher->get('_limit');
        $offset  = $paramFetcher->get('_offset');

        return array($scopes, $sort, $limit, $offset);
    }

    /**
     * @param View $view
     * @param $offset
     * @param $limit
     * @param $total
     */
    protected function setContentRangeHeader(View $view, $offset, $limit, $total)
    {
        if ($offset < $total) {
            $end = isset($limit) ? $offset + $limit : $total;
            $end = $end > $total ? $total : $end;

            $view->setHeader('Content-Range', "items $offset-$end/$total");
        }
    }

    /**
     * @param ParamFetcherInterface $paramFetcher
     * @return array
     */
    protected function getFields(ParamFetcherInterface $paramFetcher)
    {
        $fields = $paramFetcher->all();
        $fields = $this->removeSystemParameters($fields);

        return $fields;
    }

    /**
     * @param array $fields
     * @return array
     */
    private function removeSystemParameters(array $fields)
    {
        foreach ($fields as $fieldName => $fieldValue) {
            if (strpos($fieldName, '_') === 0) {
                unset($fields[$fieldName]);
            }
        }

        return $fields;
    }
}
