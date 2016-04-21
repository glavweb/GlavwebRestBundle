<?php

namespace Glavweb\RestBundle\EventListener;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Common\Annotations\Reader;
use Glavweb\CoreBundle\Mapping\Annotation\ImagineFilters;
use Glavweb\UploaderBundle\Entity\Media;
use Glavweb\UploaderBundle\Helper\MediaHelper;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use JMS\Serializer\EventDispatcher\PreSerializeEvent;
use Liip\ImagineBundle\Templating\Helper\ImagineHelper;
use Symfony\Bundle\FrameworkBundle\Templating\Helper\AssetsHelper;
use Symfony\Component\HttpFoundation\RequestStack;
use Vich\UploaderBundle\Metadata\MetadataReader;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

/**
 * Class AbstractSerializationListener
 * @package Glavweb\RestBundle\EventListener
 */
abstract class AbstractSerializationListener implements EventSubscriberInterface
{
    /**
     * @var array
     */
    private static $cache;

    /**
     * @var AssetsHelper
     */
    protected $requestStack;

    /**
     * @var UploaderHelper
     */
    protected $uploaderHelper;

    /**
     * @var Registry
     */
    protected $doctrine;

    /**
     * @var ImagineHelper
     */
    protected $imagineHelper;
    
    /**
     * @var MetadataReader
     */
    protected $metadataReader;
    
    /**
     * @var Reader
     */
    protected $annotationsReader;

    /**
     * @var MediaHelper
     */
    protected $mediaHelper;

    /**
     * @param RequestStack   $requestStack
     * @param UploaderHelper $uploaderHelper
     * @param MetadataReader $metadataReader
     * @param ImagineHelper  $imagineHelper
     * @param Registry       $doctrine
     * @param Reader         $annotationsReader
     * @param MediaHelper    $mediaHelper
     */
    public function __construct(RequestStack $requestStack, UploaderHelper $uploaderHelper, MetadataReader $metadataReader, ImagineHelper $imagineHelper, Registry $doctrine, Reader $annotationsReader, MediaHelper $mediaHelper)
    {
        $this->requestStack      = $requestStack;
        $this->uploaderHelper    = $uploaderHelper;
        $this->imagineHelper     = $imagineHelper;
        $this->metadataReader    = $metadataReader;
        $this->doctrine          = $doctrine;
        $this->annotationsReader = $annotationsReader;
        $this->mediaHelper       = $mediaHelper;
    }

    /**
     * @param PreSerializeEvent $event
     */
    public function onPreSerializeFile(PreSerializeEvent $event)
    {
        $entity    = $event->getObject();
        $type      = $event->getType();
        $className = $type['name'];
        $request   = $this->requestStack->getCurrentRequest();

        $isUploadable = $this->metadataReader->isUploadable($className);
        if (!$isUploadable) {
            return ;
        }

        $cacheKey = md5($className . '_' . $entity->getId());
        if (isset(self::$cache[$cacheKey])) {
            return;
        }
        self::$cache[$cacheKey] = true;

        $uploadableFields = $this->metadataReader->getUploadableFields($className);
        foreach ($uploadableFields as $uploadableField) {
            $propertyName     = $uploadableField['propertyName'];
            $fileNameProperty = $uploadableField['fileNameProperty'];

            $getterFile = 'get' . $fileNameProperty;
            if ($entity->$getterFile()) {
                $setterFile = 'set' . $fileNameProperty;
                $originUrl = $this->uploaderHelper->asset($entity, $propertyName);

                if ($originUrl) {
                    /** @var ImagineFilters $imagineFiltersAnnotation */
                    $reflectionClass = new \ReflectionClass($className);
                    $imagineFiltersAnnotation = $this->annotationsReader->getPropertyAnnotation(
                        $reflectionClass->getProperty($fileNameProperty),
                        new ImagineFilters()
                    );

                    if ($imagineFiltersAnnotation) {
                        $urls = [];
                        foreach ($imagineFiltersAnnotation->getFilters() as $filter) {
                            $urls[$filter] = $this->imagineHelper->filter($originUrl, $filter);
                        }

                        $property = $imagineFiltersAnnotation->getProperty();
                        $entity->$property = $urls;
                    }
                }

                if ($request) {
                    $originUrl = $request->getSchemeAndHttpHost() . $originUrl;
                }

                $entity->$setterFile($originUrl);
            }
        }
    }

    /**
     * @param PreSerializeEvent $event
     */
    public function onPerSerializeGlavwebMediaFile(PreSerializeEvent $event)
    {
        $entity    = $event->getObject();
        $type      = $event->getType();
        $className = $type['name'];
        $request   = $this->requestStack->getCurrentRequest();
        $reflectionClass = new \ReflectionClass($className);

        $media = array();

        $classProperties = $reflectionClass->getProperties();
        foreach ($classProperties as $property) {
            $uploadableFieldAnnotation = $this->annotationsReader->getPropertyAnnotation(
                $property,
                'Glavweb\UploaderBundle\Mapping\Annotation\UploadableField'
            );

            if ($uploadableFieldAnnotation) {
                /** @var ImagineFilters $imagineFiltersAnnotation */
                $imagineFiltersAnnotation = $this->annotationsReader->getPropertyAnnotation(
                    $property,
                    'Glavweb\CoreBundle\Mapping\Annotation\ImagineFilters'
                );

                if (!$imagineFiltersAnnotation) {
                    continue;
                }
                $imagineFilterProperty = $imagineFiltersAnnotation->getProperty();

                $getter = 'get' . ucfirst($property->getName());
                $items = $entity->$getter();

                if (!is_array($items) && !$items instanceof \Traversable) {
                    continue;
                }

                /** @var Media $item */
                foreach ($items as $item) {
                    $originUrl = null;
                    $thumbnails = [];
                    if ($item->getContentPath()) {
                        $originUrl = $this->mediaHelper->getContentPath($item);

                        foreach ($imagineFiltersAnnotation->getFilters() as $filter) {
                            $thumbnails[$filter] = $this->imagineHelper->filter($originUrl, $filter);
                        }
                    }

                    if ($request) {
                        $originUrl = $request->getSchemeAndHttpHost() . $originUrl;
                    }

                    $media[] = array(
                        'originUrl'   => $originUrl,
                        'thumbnails'  => $thumbnails,
                        'name'        => $item->getName(),
                        'description' => $item->getDescription(),
                    );
                }

                $entity->$imagineFilterProperty = $media;
            }
        }
    }
}