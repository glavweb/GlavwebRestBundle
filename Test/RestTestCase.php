<?php

namespace Glavweb\RestBundle\Test;

use Glavweb\RestBundle\Determiner\Action\CreateActionDeterminer;
use Glavweb\RestBundle\Determiner\Action\ListActionDeterminer;
use Glavweb\RestBundle\Determiner\Action\PatchActionDeterminer;
use Glavweb\RestBundle\Determiner\Action\PostActionDeterminer;
use Glavweb\RestBundle\Determiner\Action\PutActionDeterminer;
use Glavweb\RestBundle\Determiner\Action\UpdateActionDeterminer;
use Glavweb\RestBundle\Determiner\Action\ViewActionDeterminer;
use Glavweb\RestBundle\Transformer\TestDataTransformer;
use Glavweb\RestBundle\Util\FileUtil;
use Liip\FunctionalTestBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

/**
 * Class RestTestCase
 * @package Glavweb\RestBundle\Test
 */
abstract class RestTestCase extends WebTestCase
{
    const MODE_CHECK_FIRST = 'check_first';

    /**
     * @var Client|null
     */
    private static $storedClient = null;

    /**
     * @var array
     */
    private static $storedAuthenticateResponse = [];

    /**
     * @var array
     */
    private static $storedFixtureObjects = [];

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var array
     */
    protected $authenticateResponse = array();

    /**
     * @var bool
     */
    protected $useStored = false;

    /**
     * Set Up
     */
    public function setUp()
    {
        if ($this->useStored) {
            $client = $this->getStoredClient();

        } else {
            $client = $this->createCurrentClient();
        }

        $this->client = $client;
    }

    /**
     * @return Client
     */
    protected function createCurrentClient()
    {
        $container = $this->getContainer();
        $environment = $container->get('kernel')->getEnvironment();
        $httpHost    = $container->getParameter('http_host');

        return static::createClient(array(
            'environment' => $environment,
            'debug'       => false,
        ), ['HTTP_HOST' => $httpHost]);
    }

    /**
     * @param string $username
     * @param string $password
     * @return array
     */
    public function authenticate($username, $password)
    {
        if ($this->useStored) {
            $authenticateResponse = $this->getStoredAuthenticateResponse($username, $password);

        } else {
            $authenticateResponse = $this->doAuthenticate($username, $password);
        }

        return $this->authenticateResponse = $authenticateResponse;
    }

    /**
     * @param array $fixtureFiles
     * @param bool $append
     * @param string $username
     * @param string $password
     * @return array
     */
    protected function loadFixturesAndAuthenticate($fixtureFiles = [], $append = false, $username = 'admin', $password = 'qwerty')
    {
        if ($this->useStored) {
            $objects = $this->getStoredFixtureFiles($fixtureFiles, $append);

        } else {
            $objects = $this->loadFixtureFiles($fixtureFiles, $append);
        }

        if ($username !== null) {
            $this->authenticate($username, $password);
        }

        return $objects;
    }

    /**
     * @param string $username
     * @param string $password
     * @return array
     */
    private function doAuthenticate($username, $password)
    {
        $this->client->request('POST', '/api/sign-in', array(
            'username' => $username,
            'password' => $password
        ), array(), array('HTTP_ACCEPT' => 'application/json'));
        $loginResponse = json_decode($this->client->getResponse()->getContent());

        $authenticateResponse = array(
            'token'    => (isset($loginResponse->apiToken) ? $loginResponse->apiToken : null),
            'expireAt' => (isset($loginResponse->createdAt) ? $loginResponse->createdAt : null),
            'username' => (isset($loginResponse->login) ? $loginResponse->login : null)
        );

        return $authenticateResponse;
    }

    /**
     * @return Client
     */
    protected function getStoredClient()
    {
        if (!self::$storedClient) {
            self::$storedClient = $this->createCurrentClient();
        }

        return self::$storedClient;
    }

    /**
     * @param $username
     * @param $password
     * @return array
     */
    public function getStoredAuthenticateResponse($username, $password)
    {
        $cacheKey = md5($username . '_' . $password);
        if (!isset(self::$storedAuthenticateResponse[$cacheKey])) {
            self::$storedAuthenticateResponse[$cacheKey] = $this->doAuthenticate($username, $password);
        }

        return self::$storedAuthenticateResponse[$cacheKey];
    }

    /**
     * @param $fixtureFiles
     * @param $append
     * @return array
     */
    public function getStoredFixtureFiles($fixtureFiles, $append)
    {
        $cacheKey = md5(implode('__' , $fixtureFiles) . '__' . (int)$append);
        if (!isset(self::$storedFixtureObjects[$cacheKey])) {
            self::$storedFixtureObjects[$cacheKey] = $this->loadFixtureFiles($fixtureFiles, $append);
            self::$storedAuthenticateResponse = []; // @todo ugly solution

        } else {
            $em = $this->getContainer()->get('doctrine')->getManager();
            foreach (self::$storedFixtureObjects[$cacheKey] as $model) {
                $em->persist($model);
            }
        }

        return self::$storedFixtureObjects[$cacheKey];
    }

    /**
     * @return Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @param string $url
     * @param array $data
     */
    public function queryRequest($url, array $data = [])
    {
        $this->restRequest('GET', $url, $data);
    }

    /**
     * @param string $url
     * @param array $data
     * @param array $files
     */
    public function createRequest($url, array $data = [], array $files = [])
    {
        $this->restRequest('POST', $url, $data, $files);
    }

    /**
     * @param string $url
     * @param array $data
     */
    public function updateRequest($url, array $data = [])
    {
        $this->restRequest('PUT', $url, $data);
    }

    /**
     * @param string $url
     * @param array $data
     */
    public function patchRequest($url, array $data = [])
    {
        $this->restRequest('PATCH', $url, $data);
    }

    /**
     * @param string $url
     * @param array $data
     */
    public function deleteRequest($url, array $data = [])
    {
        $this->restRequest('DELETE', $url, $data);
    }

    /**
     * @param string $url
     * @param array $data
     */
    public function linkRequest($url, array $data = [])
    {
        $this->restRequest('LINK', $url, $data);
    }

    /**
     * @param string $url
     * @param array $data
     */
    public function unlinkRequest($url, array $data = [])
    {
        $this->restRequest('UNLINK', $url, $data);
    }

    /**
     * @param string $method
     * @param string $url
     * @param array $data
     * @param array $files
     */
    public function restRequest($method, $url, array $data, array $files = [])
    {
        $this->client->request($method, $url,
            $data,
            $files,
            array_merge($this->authenticateResponse, [
//                'Content-Type' => 'multipart/form-data',
                'HTTP_ACCEPT' => 'application/json',
            ])
        );
    }

    /**
     * @return mixed
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
     * @return string
     */
    public function getSourceClass()
    {
        $testClass  = get_class($this);
        $restClass  = null;
        $bundleName = $this->getBundleName($testClass);

        $prefixTestsPath = $bundleName . '\\Tests';
        $pos = strpos($testClass, $prefixTestsPath);
        if ($pos === 0) {
            $testClass = substr($testClass, strlen($prefixTestsPath));

            if (substr($testClass, -4) == 'Test') {
                $restClass = $bundleName . substr($testClass, 0, -4);
            }
        }

        return $restClass;
    }

    /**
     * @param string $class
     * @return string|null
     */
    public function getBundleName($class)
    {
        $kernel = $this->getContainer()->get('kernel');

        foreach ($kernel->getBundles() as $bundle) {
            /** @var BundleInterface $bundle */
            if (strpos($class, $bundle->getNamespace() . '\\') === 0) {
                return $bundle->getName();
            }
        }

        return null;
    }

    /**
     * @param Response $response
     * @param array $expectedData
     * @return bool
     */
    public function findInJsonResponse(Response $response, array $expectedData = array())
    {
        $actualJson = $response->getContent();

        $actualData = json_decode($actualJson, true);
        foreach ($actualData as $actualItem) {
            if ($this->findDataInItem($actualItem, $expectedData)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Asserts that two given JSON encoded objects or arrays are equal.
     *
     * @param string $expectedJson
     * @param string $actualJson
     * @param string $message
     */
    public static function assertJsonStringEqualsJsonString($expectedJson, $actualJson, $message = '')
    {
        self::assertJson($expectedJson, $message);
        self::assertJson($actualJson, $message);

        $expected = json_decode($expectedJson, true);
        $actual   = json_decode($actualJson, true);

        $dataTransformer = new TestDataTransformer($expected, $actual);
        $expected = $dataTransformer->getExpectedData();
        $actual   = $dataTransformer->getActualData();

        self::assertEquals($expected, $actual, $message);
    }

    /**
     * @param Response $response
     * @param int $statusCode
     * @param array $expectedData
     * @param string $mode
     * @param null $message
     */
    public function assertJsonResponse(Response $response, $statusCode = 200, array $expectedData = array(), $mode = null, $message = null)
    {
        $actualJson = $response->getContent();

        $this->assertEquals(
            $statusCode,
            $response->getStatusCode(),
            $actualJson
        );

        $this->assertJson($actualJson, $actualJson);

        $this->assertTrue(
            $response->headers->contains('Content-Type', 'application/json'),
            $response->headers
        );

        if ($expectedData) {
            $actualData = json_decode($actualJson, true);

            $dataTransformer = new TestDataTransformer($expectedData, $actualData);
            if ($mode == self::MODE_CHECK_FIRST) {
                $dataTransformer->setModeCheckFirst(true);
            }

            $expectedData = $dataTransformer->getExpectedData();
            $actualData   = $dataTransformer->getActualData();

            if (!$message) {
                $message = $actualJson;
            }

            $this->assertEquals($expectedData, $actualData, $message);
        }
    }

    /**
     * Asserts that the HTTP response code of the last request performed by
     * $client matches the expected code. If not, raises an error with more
     * information.
     *
     * @param $expectedStatusCode
     * @param Client $client
     */
    public function assertStatusCode($expectedStatusCode, Client $client)
    {
        self::assertEquals($expectedStatusCode, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());
    }

    /**
     * @param string $url
     * @param string $expectedContentType
     * @param array  $thumbnails
     */
    public function assertContentTypeUploadedFile($url, $expectedContentType, array $thumbnails = [])
    {
        $actualContentType = FileUtil::getFileContentType($this->getAbsoluteUri($url));
        $this->assertEquals($expectedContentType, $actualContentType);

        foreach ($thumbnails as $thumbnailUrl) {
            $this->assertContentTypeUploadedFile($thumbnailUrl, $expectedContentType);
        }
    }

    /**
     * @param string $uri
     * @param array $values
     * @param array $files
     * @param array $expected
     * @param CreateActionDeterminer $determiner
     */
    public function assertCreateRestAction($uri, array $values, array $files, array $expected, CreateActionDeterminer $determiner = null)
    {
        $determineValues = [];
        $determineFiles  = [];
        if ($determiner) {
            $determineValues = $determiner->determineValues();
            $determineFiles  = $determiner->determineFiles();
        }

        $values = array_merge($determineValues, $values);
        $files = array_merge($determineFiles, $files);

        // Create
        $client = $this->getClient();
        $this->createRequest($uri, $values, $files);
        $this->assertStatusCode(201, $client);

        // Get
        $apiViewUrl = $client->getResponse()->headers->get('Location');
        $this->queryRequest($apiViewUrl);

        $determineExpected = [];
        if ($determiner) {
            $determineExpected = $determiner->determineExpected($values);
        }
        $expected = array_merge($determineExpected, $expected);

        $this->assertJsonResponse($client->getResponse(), 200, $expected);
    }

    /**
     * @param string $uri
     * @param array $expected
     * @param ViewActionDeterminer $determiner
     */
    public function assertViewRestAction($uri, array $expected, ViewActionDeterminer $determiner = null)
    {
        // View
        $client = $this->getClient();
        $this->queryRequest($uri);

        $determineExpected = [];
        if ($determiner) {
            $determineExpected = $determiner->determineExpected();
        }

        $expected = array_merge($determineExpected, $expected);
        $this->assertJsonResponse($client->getResponse(), 200, $expected);
    }

    /**
     * @param string $uri
     * @param array $cases
     * @param ListActionDeterminer $determiner
     */
    public function assertListRestAction($uri, array $cases, ListActionDeterminer $determiner = null)
    {
        $determineCases = [];
        if ($determiner) {
            $determineCases = $determiner->determineCases($uri);
        }

        $cases = array_merge($determineCases, $cases);
        $cases = array_filter($cases, function ($item) {
            return (bool)$item;
        });

        // Test list output (list scope)

        // Test cases
        $client = $this->getClient();
        foreach ($cases as $fieldName => $case) {
            $values   = $case['values'];
            $expected = $case['expected'];

            $this->queryRequest($uri, $values);
            $this->assertJsonResponse($client->getResponse(), 200, $expected, null, 'case: ' . $fieldName);
        }
    }

    /**
     * @param string $uri
     * @param array $values
     * @param array $expected
     * @param UpdateActionDeterminer $determiner
     */
    public function assertUpdateRestAction($uri, array $values, array $expected = [], UpdateActionDeterminer $determiner = null)
    {
        $determineValues = [];
        if ($determiner) {
            $determineValues = $determiner->determineValues();
        }
        $values = array_merge($determineValues, $values);

        // Update
        $client = $this->getClient();
        $this->updateRequest($uri, $values);
        $this->assertStatusCode(200, $client);

        // Get
        $this->queryRequest($uri);

        $determineExpected = [];
        if ($determiner) {
            $determineExpected = $determiner->determineExpected($values);
        }
        $expected = array_merge($determineExpected, $expected);

        $this->assertJsonResponse($client->getResponse(), 200, $expected);
    }

    /**
     * @param string $uri
     * @param array $cases
     * @param PatchActionDeterminer $determiner
     */
    public function assertPatchRestAction($uri, array $cases, PatchActionDeterminer $determiner = null)
    {
        $determineCases = [];
        if ($determiner) {
            $determineCases = $determiner->determineCases();
        }

        $cases = array_merge($determineCases, $cases);
        $cases = array_filter($cases, function ($item) {
            return (bool)$item;
        });

        // Test patch
        $client = $this->getClient();

        foreach ($cases as $case) {
            $values   = $case['values'];
            $expected = $case['expected'];
            $this->patchRequest($uri, $values);

            $this->queryRequest($uri);
            $this->assertJsonResponse($client->getResponse(), 200, $expected);
        }
    }

    /**
     * @param string $uri
     */
    public function assertDeleteRestAction($uri)
    {
        $client = $this->getClient();
        $this->deleteRequest($uri);
        $this->assertStatusCode(204, $client);

        $this->queryRequest($uri);
        $this->assertStatusCode(404, $client);
    }

    /**
     * @param $actualItem
     * @param $expectedData
     * @return bool
     */
    private function findDataInItem($actualItem, $expectedData)
    {
        foreach ($expectedData as $expectedItemName => $expectedItemValue) {
            $isExist =
                isset($actualItem[$expectedItemName]) &&
                $actualItem[$expectedItemName] == $expectedItemValue
            ;

            if (!$isExist) {
                return false;
            }
        }

        return true;
    }
}
