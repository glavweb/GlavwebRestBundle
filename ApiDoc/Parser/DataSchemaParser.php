<?php

namespace Glavweb\RestBundle\ApiDoc\Parser;

use Glavweb\DatagridBundle\DataSchema\DataSchemaFactory;
use Glavweb\DatagridBundle\Loader\Yaml\DataSchemaYamlLoader;
use Nelmio\ApiDocBundle\Parser\ParserInterface;
use Symfony\Component\Config\FileLocator;

/**
 * Class DataSchemaParser
 * @package Glavweb\RestBundle\ApiDoc\Parser
 */
class DataSchemaParser implements ParserInterface
{
    /**
     * @var DataSchemaFactory
     */
    protected $dataSchemaFactory;

    /**
     * @var string
     */
    protected $dataSchemaDir;

    /**
     * DataSchemaParser constructor.
     *
     * @param DataSchemaFactory $dataSchemaFactory
     * @param string $dataSchemaDir
     */
    public function __construct(DataSchemaFactory $dataSchemaFactory, $dataSchemaDir)
    {
        $this->dataSchemaFactory = $dataSchemaFactory;
        $this->dataSchemaDir     = $dataSchemaDir;
    }

    /**
     * @param array $item
     * @return bool
     */
    public function supports(array $item)
    {
        return !$item['class'] && isset($item['options']['data_schema']);
    }

    /**
     * @param array $item
     */
    public function parse(array $item)
    {
        $schemaFile = $item['options']['data_schema'];

        $dataSchema = $this->dataSchemaFactory->createDataSchema($schemaFile);
        $configuration = $dataSchema->getConfiguration();

        return $this->getParameters($configuration);
    }

    /**
     * @param array $configuration
     * @return array
     */
    protected function getParameters(array $configuration)
    {
        $parameters = [];

        if (isset($configuration['properties'])) {
            $properties = $configuration['properties'];
            
            foreach ($properties as $name => $info) {
                $parameters[$name] = $this->getItemMetaData($info);

                if (isset($info['properties'])) {
                    $parameters[$name]['children'] = $this->getParameters($info);
                }
            }
        }

        return $parameters;
    }

    /**
     * @param array$info
     * @return array
     */
    public function getItemMetaData(array $info)
    {
        $type = $info['type'];

        $meta = [
            'dataType'    => $type == 'NULL' ? null : $type,
            'actualType'  => $type,
            'subType'     => null,
            'required'    => null,
            'description' => null,
            'readonly'    => null,
            'default'     => null,
        ];

        return $meta;
    }
}