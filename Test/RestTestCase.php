<?php

namespace Glavweb\RestBundle\Test;

use Glavweb\RestBundle\Test\Authenticate\AuthenticateResponse;
use Glavweb\RestBundle\Test\Authenticate\AuthenticatorInterface;
use Glavweb\RestBundle\Test\Guesser\Action\CreateActionGuesser;
use Glavweb\RestBundle\Test\Guesser\Action\ListActionGuesser;
use Glavweb\RestBundle\Test\Guesser\Action\PatchActionGuesser;
use Glavweb\RestBundle\Test\Guesser\Action\UpdateActionGuesser;
use Glavweb\RestBundle\Test\Guesser\Action\ViewActionGuesser;
use Glavweb\RestBundle\Test\Transformer\DataTransformer;
use Liip\FunctionalTestBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Component\HttpFoundation\Response;

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
     * @var AuthenticateResponse
     */
    protected $authenticateResponse;

    /**
     * Set Up
     */
    public function setUp()
    {
        $this->client = $this->createCurrentClient();
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
        $httpHost    = $container->getParameter('http_host');

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

        $dataTransformer = new DataTransformer($expected, $actual);
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
    public function assertJsonResponse(Response $response, $statusCode = 200, array $expectedData = [], $mode = null, $message = null)
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

            $dataTransformer = new DataTransformer($expectedData, $actualData);
            if ($mode == DataTransformer::MODE_CHECK_FIRST) {
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
     * @param string $uri
     * @param array $values
     * @param array $files
     * @param array $expected
     * @param CreateActionGuesser $guesser
     */
    public function assertCreateRestAction($uri, array $values, array $files, array $expected, CreateActionGuesser $guesser = null)
    {
        $guessValues = [];
        $guessFiles  = [];
        if ($guesser) {
            $guessValues = $guesser->guessValues();
            $guessFiles  = $guesser->guessFiles();
        }

        $values = array_merge($guessValues, $values);
        $files = array_merge($guessFiles, $files);

        // Create
        $client = $this->getClient();
        $this->sendCreateRestRequest($uri, $values, $files);
        $this->assertStatusCode(201, $client);

        // Get
        $apiViewUrl = $client->getResponse()->headers->get('Location');
        $this->sendQueryRestRequest($apiViewUrl);

        $guessExpected = [];
        if ($guesser) {
            $guessExpected = $guesser->guessExpected($values);
        }
        $expected = array_merge($guessExpected, $expected);

        $this->assertJsonResponse($client->getResponse(), 200, $expected);
    }

    /**
     * @param ViewActionGuesser $guesser
     * @param array $additionalExpected
     */
    public function assertViewRestActionByGuesser(ViewActionGuesser $guesser, array $additionalExpected = [])
    {
        $client = $this->getClient();

        $scopes = $guesser->getScopes();
        foreach ($scopes as $scope) {
            $this->sendQueryRestRequest($guesser->getUri(['_scope' => $scope]));
            $this->assertStatusCode(200, $client);

            // Test scope stricture
            $scopeConfig = $guesser->getScopeConfig($scope);
            $data = $this->getResponseData();

            // assert scope structure
            $this->assertDataStructure($data, $scopeConfig);

            // assert data
            $guessExpected = $guesser->guessExpected($scope);
            $expected = array_merge($guessExpected, $additionalExpected);
            $this->assertJsonResponse($client->getResponse(), 200, $expected);
        }
    }

    /**
     * @param ListActionGuesser $guesser
     * @param array $additionalCases
     */
    public function assertListRestActionByGuesser(ListActionGuesser $guesser, array $additionalCases = [])
    {
        $client = $this->getClient();

        // Test list output (list scope)
        $scopes = $guesser->getScopes();
        foreach ($scopes as $scope) {
            $this->sendQueryRestRequest($guesser->getUri(['_scope' => $scope]));
            $this->assertStatusCode(200, $client);

            // Test scope stricture
            $scopeConfig = $guesser->getScopeConfig($scope);
            $data = $this->getResponseData();

            if (!isset($data[0])) {
                $this->assertTrue(false, 'Data not found.');
            }

            $this->assertDataStructure($data[0], $scopeConfig);
        }

        $guessCases = $guesser->guessCases();
        $cases = array_merge($guessCases, $additionalCases);
        $cases = array_filter($cases, function ($item) {
            return (bool)$item;
        });

        // Test cases
        $uri = $guesser->getUri();
        foreach ($cases as $caseName => $case) {
            $values   = $case['values'];
            $expected = $case['expected'];

            $this->sendQueryRestRequest($uri, $values);
            $this->assertJsonResponse($client->getResponse(), 200, $expected, null, 'case: ' . $caseName);
        }
    }

    /**
     * @param string $uri
     * @param array $values
     * @param array $expected
     * @param UpdateActionGuesser $guesser
     */
    public function assertUpdateRestAction($uri, array $values, array $expected = [], UpdateActionGuesser $guesser = null)
    {
        $guessValues = [];
        if ($guesser) {
            $guessValues = $guesser->guessValues();
        }
        $values = array_merge($guessValues, $values);

        // Update
        $client = $this->getClient();
        $this->sendUpdateRestRequest($uri, $values);
        $this->assertStatusCode(200, $client);

        // Get
        $this->sendQueryRestRequest($uri);

        $guessExpected = [];
        if ($guesser) {
            $guessExpected = $guesser->guessExpected($values);
        }
        $expected = array_merge($guessExpected, $expected);

        $this->assertJsonResponse($client->getResponse(), 200, $expected);
    }

    /**
     * @param string $uri
     * @param array $cases
     * @param PatchActionGuesser $guesser
     */
    public function assertPatchRestAction($uri, array $cases, PatchActionGuesser $guesser = null)
    {
        $guessCases = [];
        if ($guesser) {
            $guessCases = $guesser->guessCases();
        }

        $cases = array_merge($guessCases, $cases);
        $cases = array_filter($cases, function ($item) {
            return (bool)$item;
        });

        // Test patch
        $client = $this->getClient();

        foreach ($cases as $case) {
            $values   = $case['values'];
            $expected = $case['expected'];
            $this->sendPatchRestRequest($uri, $values);

            $this->sendQueryRestRequest($uri);
            $this->assertJsonResponse($client->getResponse(), 200, $expected);
        }
    }

    /**
     * @param string $uri
     */
    public function assertDeleteRestAction($uri)
    {
        $client = $this->getClient();
        $this->sendDeleteRestRequest($uri);
        $this->assertStatusCode(204, $client);

        $this->sendQueryRestRequest($uri);
        $this->assertStatusCode(404, $client);
    }

    /**
     * @param array $data
     * @param array $scopeConfig
     * @return void
     */
    protected function assertDataStructure(array $data, array $scopeConfig)
    {
        $diff = array_diff_key($scopeConfig, $data);

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
}
