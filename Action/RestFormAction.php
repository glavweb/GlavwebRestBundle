<?php

namespace Glavweb\RestBundle\Action;

use AppBundle\Form\Project\ApiUploadedFile;
use Doctrine\ORM\PersistentCollection;
use FOS\RestBundle\View\View;
use Glavweb\ActionBundle\Action\ParameterBag;
use Glavweb\ActionBundle\Action\Response;
use Glavweb\ActionBundle\Action\StandardAction;
use JMS\Serializer\SerializationContext;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Class RestFormAction
 *
 * @package Glavweb\RestBundle\Action
 * @author     Andrey Nilov <nilov@glavweb.ru>
 * @copyright  Copyright (c) 2010-2014 Glavweb, Russia. (http://glavweb.ru)
 */
class RestFormAction extends StandardAction
{
    /**
     * Rules of parameters
     * @var array
     */
    protected $parameterRules = array(
        'request'              => '\Symfony\Component\HttpFoundation\Request',
        'formType'             => '\Symfony\Component\Form\AbstractType',
        'entity'               => ':object',
        'cleanForm'            => ':bool|:null',
        'cleanRequestData'     => ':bool|:null',
        'getContent'           => ':bool|:null',
        'formOptions'          => ':array|:null',
        'serializationContext' => '\JMS\Serializer\SerializationContext|:null',
        'onPreValidation'      => '\Closure|:null',
        'onPostValidation'     => '\Closure|:null',
        'onPostPersist'        => '\Closure|:null',
        'onSuccess'            => '\Closure|:null',
        'onFailure'            => '\Closure|:null'
    );

    /**
     * An array default values for response
     * @var array
     */
    protected $defaultResponse = array(
        'response' => null
    );

    /**
     * @param Response     $response
     * @param ParameterBag $parameterBag
     * @return bool|void
     */
    protected function doExecute(Response $response, ParameterBag $parameterBag)
    {
        /** @var Request $request */
        /** @var AbstractType $formType */
        /** @var object $entity */
        /** @var bool $cleanForm */
        /** @var bool $cleanRequestData */
        /** @var bool $getContent */
        /** @var array $formOptions */
        /** @var SerializationContext $serializationContext */
        /** @var \Closure $onPreValidation */
        /** @var \Closure $onPostValidation */
        /** @var \Closure $onPostPersist */
        /** @var \Closure $onSuccess */
        /** @var \Closure $onFailure  */
        $request              = $parameterBag->get('request');
        $formType             = $parameterBag->get('formType');
        $entity               = $parameterBag->get('entity');
        $cleanForm            = $parameterBag->get('cleanForm');
        $cleanRequestData     = $parameterBag->get('cleanRequestData', true);
        $getContent           = $parameterBag->get('getContent');
        $formOptions          = $parameterBag->get('formOptions', array());
        $serializationContext = $parameterBag->get('serializationContext');
        $onPreValidation      = $parameterBag->get('onPreValidation');
        $onPostValidation     = $parameterBag->get('onPostValidation');
        $onPostPersist        = $parameterBag->get('onPostPersist');
        $onSuccess            = $parameterBag->get('onSuccess');
        $onFailure            = $parameterBag->get('onFailure ');

        $view         = new View();
        $httpResponse = $view->getResponse();

        $requestData = array_merge(
            $request->request->all(),
            $request->files->all()
        );

        if ($serializationContext) {
            $view->setSerializationContext($serializationContext);
        }

        $form = $this->getFormFactory()->createNamed(null, $formType, $entity,
            array_merge(
                array('csrf_protection' => false),
                $formOptions
            )
        );

        if ($cleanRequestData) {
            $requestData = $this->cleanRequestData($requestData, $form);
        }

        if ($cleanForm) {
            $this->cleanForm($requestData, $form);
        }

        $this->prepareFormCollections($request, $form);

        /**
         * Pre validation
         */
        $this->onPreValidation($request, $form, $entity, $httpResponse);

        if ($onPreValidation instanceof \Closure) {
            $onPreValidation($request, $form, $entity, $httpResponse);
        }

        if ($httpResponse->getStatusCode() != HttpResponse::HTTP_OK) {
            $response->response = $view;

            return true;
        }

        $form->submit($requestData);

        if ($form->isValid()) {
            if ($onPostValidation instanceof \Closure) {
                $onPostValidation($request, $form, $entity, $httpResponse);

                if ($httpResponse->getStatusCode() != HttpResponse::HTTP_OK) {
                    $response->response = $view;

                    return true;
                }
            }

            $isEditAction = $entity->getId();
            $statusCode = $isEditAction ? HttpResponse::HTTP_OK : HttpResponse::HTTP_CREATED;

            /** @var \Doctrine\Common\Persistence\ObjectManager $em */
            $em = $this->getDoctrine()->getManager();
            $em->persist($entity);

            if ($onPostPersist instanceof \Closure) {
                $onPostPersist($request, $form, $entity, $httpResponse);
            }

            $em->flush();

            $httpResponse->setStatusCode($statusCode);
            if ($isEditAction) {
                if ($getContent) {
                    $em->refresh($entity);
                    $view->setData($entity);
                }

            } else {
                $view->setData($entity->getId());
            }

            if ($onSuccess instanceof \Closure) {
                $onSuccess($request, $form, $entity, $httpResponse);
            }

            $response->response = $view;

            return true;
        }

        if ($onFailure instanceof \Closure) {
            $onFailure($request, $form, $entity, $httpResponse);

            $response->response = $view;

            return true;
        }

        $response->response = $form;

        return false;
    }

    /**
     * Remove unnecessary fields from form
     *
     * @param array $requestData
     * @param Form $form
     */
    private function cleanForm($requestData, Form $form)
    {
        $allowedField = array_keys($requestData);

        /** @var FormInterface[] $formFields */
        $formFields = $form->all();
        foreach ($formFields as $formField) {
            $fieldName = $formField->getName();

            if (!in_array($fieldName, $allowedField)) {
                $form->remove($fieldName);
            }
        }
    }

    /**
     * @param Request $request
     * @param Form    $form
     */
    private function prepareFormCollections(Request $request, Form $form)
    {
        /** @var Form[] $formFields */
        $formFields = $form->all();
        foreach ($formFields as $formField) {
            $modelData = $formField->getData();
            if ($modelData instanceof PersistentCollection) {
                $collectionName = $formField->getName();
                $requestData    = $request->request->get($collectionName);

                if (!$requestData) {
                    continue;
                }

                $newCollection = $this->getPreparedCollection($modelData, $requestData);
                if ($newCollection) {
                    $request->request->set($collectionName, $newCollection);
                }
            }
        }
    }

    /**
     * @param PersistentCollection $modelData
     * @param array                $requestData
     * @return array
     */
    private function getPreparedCollection(PersistentCollection $modelData, array $requestData)
    {
        $modelPositions = array_flip(array_map(function ($item) {
            if (method_exists($item, 'getId')) {
                return $item->getId();
            }
        }, $modelData->toArray()));

        $items = [];
        $newItems = [];
        foreach ($requestData as $item) {
            $itemId = isset($item['id']) ? $item['id'] : null;

            if ($itemId) {
                $modelPosition = isset($modelPositions[$itemId]) ? $modelPositions[$itemId] : null;
                if ($modelPosition !== null) {
                    $items[$modelPosition] = $item;
                }
            } else {
                $newItems[] = $item;
            }
        }

        foreach ($newItems as $newItem) {
            $items[] = $newItem;
        }

        return $items;
    }

    /**
     * @param array $requestData
     * @param FormInterface $form
     * @return array
     */
    private function cleanRequestData(array $requestData, FormInterface $form)
    {
        foreach ($requestData as $key => $value) {
            if (!$form->has($key)) {
                unset($requestData[$key]);
            }
        }

        return $requestData;
    }

    /**
     * @param Request $request
     * @param FormInterface $form
     * @param $entity
     * @param HttpResponse $httpResponse
     */
    protected function onPreValidation(Request $request, FormInterface $form, $entity, HttpResponse $httpResponse)
    {}
}