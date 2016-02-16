<?php

namespace Glavweb\RestBundle\EventListener;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Common\Annotations\Reader;
use Glavweb\CoreBundle\Mapping\Annotation\ImagineFilters;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use JMS\Serializer\EventDispatcher\PreSerializeEvent;
use Liip\ImagineBundle\Templating\Helper\ImagineHelper;
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
     * @var UploaderHelper
     */
    private $uploaderHelper;

    /**
     * @var Registry
     */
    private $doctrine;

    /**
     * @var ImagineHelper
     */
    private $imagineHelper;
    
    /**
     * @var MetadataReader
     */
    private $metadataReader;
    
    /**
     * @var Reader
     */
    private $annotationsReader;

    /**
     * @param UploaderHelper $uploaderHelper
     * @param MetadataReader $metadataReader
     * @param ImagineHelper $imagineHelper
     * @param Registry $doctrine
     * @param Reader $annotationsReader
     */
    public function __construct(UploaderHelper $uploaderHelper, MetadataReader $metadataReader, ImagineHelper $imagineHelper, Registry $doctrine, Reader $annotationsReader)
    {
        $this->uploaderHelper    = $uploaderHelper;
        $this->imagineHelper     = $imagineHelper;
        $this->metadataReader    = $metadataReader;
        $this->doctrine          = $doctrine;
        $this->annotationsReader = $annotationsReader;
    }

    /**
     * @param PreSerializeEvent $event
     */
    public function onPreSerializeFile(PreSerializeEvent $event)
    {
        $entity    = $event->getObject();
        $type      = $event->getType();
        $className = $type['name'];

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

                $url = $originUrl;
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

                $entity->$setterFile($url);
            }
        }
    }
}