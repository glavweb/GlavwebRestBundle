<?php

namespace Glavweb\RestBundle\Test;

use Doctrine\ORM\EntityRepository;
use Glavweb\DatagridBundle\Loader\Yaml\ScopeYamlLoader;
use Glavweb\RestBundle\Faker\FileFaker;
use Glavweb\RestBundle\Test\Authenticate\AuthenticateResponse;
use Glavweb\RestBundle\Test\Authenticate\AuthenticatorInterface;
use Glavweb\RestBundle\Test\Transformer\DataTransformer;
use Glavweb\RestBundle\Util\FileUtil;
use Liip\FunctionalTestBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Component\Config\FileLocator;

/**
 * Class RestTestCase
 * @package Glavweb\RestBundle\Test
 */
abstract class RestTestCase extends WebTestCase
{
    /**
     * @var array
     */
    private static $authenticateResponseCache = [];

    /**
     * @var array
     */
    private static $fixtureObjectsCache = [];

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var FileFaker
     */
    protected $fileFaker;

    /**
     * @var AuthenticateResponse
     */
    protected $authenticateResponse;

    /**
     * Set Up
     */
    public function setUp()
    {
        $this->client     = $this->createCurrentClient();
        $this->fileFaker = $this->getContainer()->get('glavweb_rest.file_faker');
    }

    /**
     * @return Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @return Client
     */
    protected function createCurrentClient()
    {
        $container   = $this->getContainer();
        $environment = $container->get('kernel')->getEnvironment();
        
        // @todo replace to bundle config
        $httpHost = $container->getParameter('http_host');

        return static::createClient([
            'environment' => $environment,
            'debug'       => false,
        ], ['HTTP_HOST' => $httpHost]);
    }

    /**
     * @param array  $fixtureFiles
     * @param string $additionalCacheKey
     * @param bool   $append
     * @param string $omName
     * @param string $registryName
     * @return mixed
     */
    protected function loadCachedFixtureFiles(array $fixtureFiles, $additionalCacheKey, $append = false, $omName = null, $registryName = 'doctrine')
    {
        $cacheKey = md5(implode('__' , $fixtureFiles) . '__' . (int)$append) . '__' . $additionalCacheKey;
        if (!isset(self::$fixtureObjectsCache[$cacheKey])) {
            self::$fixtureObjectsCache[$cacheKey] = $this->loadFixtureFiles($fixtureFiles, $append, $omName, $registryName);

        } else {
            // Refresh objects
            $doctrine = $this->getContainer()->get('doctrine');
            $clearClasses = [];
            foreach (self::$fixtureObjectsCache[$cacheKey] as $objectName => $object) {
                $class = get_class($object);

                if (!isset($clearClasses[$class])) {
                    $doctrine->getManager()->clear($class);
                    $clearClasses[$class] = true;
                }

                self::$fixtureObjectsCache[$cacheKey][$objectName] = $this->getRepository($class)->find($object->getId());
            }
        }

        return self::$fixtureObjectsCache[$cacheKey];
    }

    /**
     * @return void
     */
    public function clearFixtureCache()
    {
        self::$fixtureObjectsCache = [];
    }

    /**
     * @param AuthenticatorInterface $authenticator
     * @param bool|false $useCache
     * @return AuthenticateResponse
     */
    public function authenticate(AuthenticatorInterface $authenticator, $useCache = false)
    {
        if ($useCache) {
            $authenticateResponse = $this->getCachedAuthenticateResponse($authenticator);

        } else {
            $authenticateResponse = $authenticator->authenticate();
        }

        return $this->authenticateResponse = $authenticateResponse;
    }

    /**
     * @param AuthenticatorInterface $authenticator
     * @return mixed
     */
    protected function getCachedAuthenticateResponse(AuthenticatorInterface $authenticator)
    {
        $cacheKey = $authenticator->getCacheKey();
        if (!isset(self::$authenticateResponseCache[$cacheKey])) {
            self::$authenticateResponseCache[$cacheKey] = $authenticator->authenticate();
        }

        return self::$authenticateResponseCache[$cacheKey];
    }

    /**
     * @return void
     */
    public function clearAuthenticateCache()
    {
        self::$authenticateResponseCache = [];
    }

    /**
     * @param string $url
     * @param array $data
     */
    public function sendQueryRestRequest($url, array $data = [])
    {
        $this->sendRestRequest('GET', $url, $data);
    }

    /**
     * @param string $url
     * @param array $data
     * @param array $files
     */
    public function sendCreateRestRequest($url, array $data = [], array $files = [])
    {
        $this->sendRestRequest('POST', $url, $data, $files);
    }

    /**
     * @param string $url
     * @param array $data
     */
    public function sendUpdateRestRequest($url, array $data = [])
    {
        $this->sendRestRequest('PUT', $url, $data);
    }

    /**
     * @param string $url
     * @param array $data
     */
    public function sendPatchRestRequest($url, array $data = [])
    {
        $this->sendRestRequest('PATCH', $url, $data);
    }

    /**
     * @param string $url
     * @param array $data
     */
    public function sendDeleteRestRequest($url, array $data = [])
    {
        $this->sendRestRequest('DELETE', $url, $data);
    }

    /**
     * @param string $url
     * @param array $data
     */
    public function sendLinkRestRequest($url, array $data = [])
    {
        $this->sendRestRequest('LINK', $url, $data);
    }

    /**
     * @param string $url
     * @param array $data
     */
    public function sendUnlinkRestRequest($url, array $data = [])
    {
        $this->sendRestRequest('UNLINK', $url, $data);
    }

    /**
     * @param string $method
     * @param string $url
     * @param array $parameters
     * @param array $files
     */
    public function sendRestRequest($method, $url, array $parameters, array $files = [])
    {
        $authenticateResponse = $this->authenticateResponse;
        $authenticateParameters = $authenticateResponse->getParameters();
        $authenticateHeaders    = $authenticateResponse->getHeaders();

        $this->client->request($method, $url,
            array_merge($authenticateParameters, $parameters),
            $files,
            array_merge($authenticateHeaders, [
                'HTTP_ACCEPT' => 'application/json',
            ])
        );
    }

    /**
     * @return array
     */
    public function getResponseData()
    {
        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        return $responseData;
    }

    /**
     * Takes a URI and converts it to absolute if it is not already absolute.
     *
     * @param string $uri A URI
     * @return string An absolute URI
     */
    public function getAbsoluteUri($uri)
    {
        // already absolute?
        if (0 === strpos($uri, 'http://') || 0 === strpos($uri, 'https://')) {
            return $uri;
        }

        $currentUri = sprintf('http%s://%s/',
            $this->client->getServerParameter('HTTPS') ? 's' : '',
            $this->client->getServerParameter('HTTP_HOST', 'localhost')
        );

        // protocol relative URL
        if (0 === strpos($uri, '//')) {
            return parse_url($currentUri, PHP_URL_SCHEME) . ':' . $uri;
        }

        // anchor?
        if (!$uri || '#' == $uri[0]) {
            return preg_replace('/#.*?$/', '', $currentUri) . $uri;
        }

        if ('/' !== $uri[0]) {
            $path = parse_url($currentUri, PHP_URL_PATH);

            if ('/' !== substr($path, -1)) {
                $path = substr($path, 0, strrpos($path, '/') + 1);
            }

            $uri = $path . $uri;
        }

        return preg_replace('#^(.*?//[^/]+)\/.*$#', '$1', $currentUri) . $uri;
    }

    /**
     * @param string $url
     * @param array $queryParameters
     * @return string
     */
    public function addQueryParametersToUrl($url, array $queryParameters)
    {
        if (!$queryParameters) {
            return $url;
        }

        $urlParts = parse_url($url);
        $query = isset($urlParts['query']) ? $urlParts['query'] : '';

        $queryData = [];
        if ($query) {
            parse_str($query, $queryData);
        }
        $query = http_build_query(array_merge($queryData, $queryParameters));

        return $urlParts['path'] . ($query ? '?' . $query : '');
    }

    /**
     * @param string $class
     * @return EntityRepository
     */
    protected function getRepository($class)
    {
        /** @var EntityRepository $repository */
        $repository = $this->getContainer()->get('doctrine')->getManager()->getRepository($class);

        return $repository;
    }

    /**
     * @param string $scopePath
     * @return array
     */
    public function getScopeConfig($scopePath)
    {
        $scopeDir = $this->getContainer()->getParameter('glavweb_datagrid.scope_dir');
        $scopeLoader = new ScopeYamlLoader(new FileLocator($scopeDir));
        $scopeLoader->load($scopePath);

        return $scopeLoader->getConfiguration();
    }

    /**
     * @param string $class
     * @return object|null
     */
    protected function getLastEntity($class)
    {
        $repository = $this->getRepository($class);

        return $repository->findOneBy([], ['id' => 'DESC']);
    }

    /**
     * @param object $entity
     * @param array $expectedData
     * @param array $actualEntityData
     * @return array
     */
    protected function getActualEntityData($entity, array $expectedData, array $actualEntityData = [])
    {
        foreach ($expectedData as $key => $value) {
            $getter = 'get' . $key;
            $entityValue = $entity->$getter();

            if (is_object($entityValue) && method_exists($entityValue, 'getId')) {
                $actualEntityData[$key] = $entityValue->getId();

            } elseif (is_array($value)) {
                foreach ($entityValue as $subEntity) {
                    $actualEntityData[$key] = $this->getActualEntityData($subEntity, $value, $actualEntityData);
                }

            } else {
                $actualEntityData[$key] = $entityValue;
            }
        }

        return $actualEntityData;
    }

    /**
     * @param array  $actualData
     * @param array  $expectedData
     * @param string $message
     */
    protected function assertDataContains(array $actualData, array $expectedData = [], $message = null)
    {
        $dataTransformer = new DataTransformer($expectedData, $actualData);

        $expectedData = $dataTransformer->getExpectedData();
        $actualData   = $dataTransformer->getActualData();

        $this->assertEquals($expectedData, $actualData, $message);
    }

    /**
     * @param array $data
     * @param array $scopeConfig
     * @return void
     */
    protected function assertDataStructure(array $data, array $scopeConfig)
    {
        $diff = array_merge(array_diff_key($scopeConfig, $data), array_diff_key($data, $scopeConfig));

        if ($diff) {
            $this->assertTrue(false, sprintf('Result data have differences "%s" with scope config', implode(', ', array_keys($diff))));
            return;
        }

        foreach ($data as $key => $value) {
            if (!isset($scopeConfig[$key])) {
                continue;
            }

            if (is_array($value)) {
                if (empty($value)) {
                    $this->assertTrue(false, sprintf('No result data for "%s" in scope config.', $key));
                    return;
                }

                $toMany = isset($value[0]);
                if ($toMany) {
                    $value = $value[0];
                }

                $this->assertDataStructure($value, $scopeConfig[$key]);
            }
        }

        $this->assertTrue(true);
    }

    /**
     * @param object $entity
     * @param array  $expectedData
     */
    protected function assertEntity($entity, array $expectedData)
    {
        $actualEntityData = $this->getActualEntityData($entity, $expectedData);

        $this->assertDataContains($actualEntityData, $expectedData);
    }

    /**
     * @param string $entityClass
     * @param string $entityId
     * @param array $expectedData
     */
    protected function assertEntityFromDb($entityClass, $entityId, array $expectedData)
    {
        $doctrine = $this->getContainer()->get('doctrine');
        $doctrine->getManager()->clear($entityClass);

        $entity = $this->getRepository($entityClass)->find($entityId);

        $this->assertTrue((bool)$entity);
        $this->assertEntity($entity, $expectedData);
    }

    /**
     * @param string $entityClass
     * @param array $expectedData
     */
    protected function assertLastEntityFromDb($entityClass, array $expectedData)
    {
        $doctrine = $this->getContainer()->get('doctrine');
        $doctrine->getManager()->clear($entityClass);

        $entity = $this->getLastEntity($entityClass);

        $this->assertTrue((bool)$entity);
        $this->assertEntity($entity, $expectedData);
    }

    /**
     * @param string $url
     * @param string $expectedContentType
     */
    public function assertContentTypeFile($url, $expectedContentType)
    {
        $actualContentType = FileUtil::getFileContentType($this->getAbsoluteUri($url));
        $this->assertEquals($expectedContentType, $actualContentType);
    }

    /**
     * @param string $url
     * @param array  $expectedData
     * @param bool   $getFirst
     */
    public function restItemTestCase($url, array $expectedData = null, $getFirst = false)
    {
        $client = $this->getClient();

        $this->sendQueryRestRequest($url);
        $this->assertStatusCode(200, $client);

        $actualData = $this->getResponseData();

        if ($getFirst) {
            if (!isset($actualData[0])) {
                $this->assertTrue(false, 'First item not found.');
            }

            $actualData = $actualData[0];
        }

        $this->assertDataContains($actualData, $expectedData);
    }

    /**
     * @param string $url
     * @param array  $scopes
     * @param bool   $getFirst
     */
    public function restScopeTestCase($url, array $scopes = [], $getFirst = false)
    {
        $client = $this->getClient();

        foreach ($scopes as $scopeName => $scopeConfig) {
            $this->sendQueryRestRequest($this->addQueryParametersToUrl($url, ['_scope' => $scopeName]));
            $this->assertStatusCode(200, $client);

            $actualData = $this->getResponseData();

            if ($getFirst) {
                if (!isset($actualData[0])) {
                    $this->assertTrue(false, 'First item not found.');
                }

                $actualData = $actualData[0];
            }

            $this->assertDataStructure($actualData, $scopeConfig);
        }
    }

    /**
     * @param string $url
     * @param array $cases
     */
    public function restListFilterTestCase($url, array $cases)
    {
        $client = $this->getClient();

        $cases = array_filter($cases, function ($item) {
            return (bool)$item;
        });

        foreach ($cases as $caseName => $case) {
            $values   = $case['values'];
            $expected = $case['expected'];

            $this->sendQueryRestRequest($url, $values);

            $this->assertStatusCode(200, $client);
            $this->assertDataContains($data = $this->getResponseData(), $expected, 'case: ' . $caseName);
        }
    }

    /**
     * @param string $url
     * @param string $entityClass
     * @param string $entityId
     */
    protected function restDeleteTestCase($url, $entityClass, $entityId)
    {
        $client  = $this->getClient();

        $this->sendDeleteRestRequest(rtrim($url, '/') . '/' .  $entityId);
        $this->assertStatusCode(204, $client);

        // Test in DB
        $doctrine = $this->getContainer()->get('doctrine');
        $doctrine->getManager()->clear($entityClass);

        $this->assertTrue($this->getRepository($entityClass)->find($entityId) === null);
    }

    /**
     * @param string $url
     * @param array $cases
     */
    protected function restUploadFileTestCase($url, array $cases)
    {
        $fileFaker = $this->getContainer()->get('glavweb_rest.file_faker');

        foreach ($cases as $case) {
            if (!isset($case['type'])) {
                throw new \RuntimeException('Type not found.');
            }

            if (!isset($case['status'])) {
                throw new \RuntimeException('Status not found.');
            }

            $type           = $case['type'];
            $expectedStatus = $case['status'];

            switch ($type) {
                case 'image':
                    $fileName  = isset($case['fileName']) ? $case['fileName'] : 'jpeg';
                    $imageType = isset($case['imageType']) ? $case['imageType'] : 'jpeg';
                    $width     = isset($case['width']) ? $case['width'] : null;
                    $height    = isset($case['height']) ? $case['height'] : null;
                    $file = $fileFaker->getFakeUploadedImage($imageType, $fileName, $width, $height);

                    break;

                case 'php':
                    $content = isset($case['content']) ? $case['content'] : '';
                    $file = $fileFaker->getFakeUploadedPhpFile($content);

                    break;

                case 'txt':
                    $content = isset($case['content']) ? $case['content'] : '';
                    $file = $fileFaker->getFakeUploadedTxtFile($content);

                    break;

                default:
                    throw new \RuntimeException(sprintf('Type %s must be "image, php or txt".', $type));
            }

            $this->sendCreateRestRequest($url, [], [
                'file' => $file
            ]);

            $this->assertStatusCode($expectedStatus, $this->client);
            
            // After callback
            if (isset($case['after'])) {
                if (!is_callable($case['after'])) {
                    throw new \RuntimeException('Attribute "after" must be callable.');
                }
                
                call_user_func($case['after']);
            }
        }
    }
}
