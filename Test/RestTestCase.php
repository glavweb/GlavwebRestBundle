<?php

namespace Glavweb\RestBundle\Test;

use Faker\Provider\Image;
use Liip\FunctionalTestBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class RestTestCase
 * @package Glavweb\RestBundle\Test
 */
abstract class RestTestCase extends WebTestCase
{
    const MODE_CHECK_FIRST = 'check_first';

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var array
     */
    protected $authenticateResponse = array();

    /**
     * Set Up
     */
    public function setUp()
    {
        $container = $this->getContainer();
        $environment = $container->get('kernel')->getEnvironment();
        $httpHost    = $container->getParameter('http_host');

        $this->client = static::createClient(array(
            'environment' => $environment,
            'debug'       => true,
        ), ['HTTP_HOST' => $httpHost]);
    }

    /**
     * @param string $username
     * @param string $password
     * @return array
     */
    public function authenticate($username, $password)
    {
        $this->client->request('POST', '/api/sign-in', array(
            'username' => $username,
            'password' => $password
        ), array(), array('HTTP_ACCEPT' => 'application/json'));
        $loginResponse = json_decode($this->client->getResponse()->getContent());

        $this->authenticateResponse = array(
            'token'    => (isset($loginResponse->apiToken) ? $loginResponse->apiToken : null),
            'expireAt' => (isset($loginResponse->createdAt) ? $loginResponse->createdAt : null),
            'username' => (isset($loginResponse->login) ? $loginResponse->login : null)
        );

        return $this->authenticateResponse;
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
        $objects = $this->loadFixtureFiles($fixtureFiles, $append);
        $this->authenticate($username, $password);

        return $objects;
    }

    /**
     * @return mixed
     */
    protected function getResponseData()
    {
        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        return $responseData;
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

        self::checkAndPrepareArrays($expected, $actual);
        self::clearActualArray($expected, $actual);

        self::assertEquals($expected, $actual, $message);
    }

    /**
     * @param Response $response
     * @param int $statusCode
     * @param array $expectedData
     * @param string $mode
     */
    protected function assertJsonResponse(Response $response, $statusCode = 200, array $expectedData = array(), $mode = null)
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

            if ($mode == self::MODE_CHECK_FIRST) {
                $expectedData = current($expectedData);
                $actualData   = current($actualData);
            }

            self::checkAndPrepareArrays($expectedData, $actualData);
            self::clearActualArray($expectedData, $actualData);

            $this->assertEquals($expectedData, $actualData, $actualJson);
        }
    }

    /**
     * @param Response $response
     * @param array $expectedData
     * @return bool
     */
    protected function findInJsonResponse(Response $response, array $expectedData = array())
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
     * @param string $expected
     * @param string $actual
     * @return mixed
     */
    private static function checkAndPrepareArrays(&$expected, &$actual)
    {
        foreach ($expected as $key => $value) {
            if (is_array($value)) {
                if (isset($actual[$key])) {
                    self::checkAndPrepareArrays($expected[$key], $actual[$key]);
                }

                continue;
            }

            if ($value == '{ignore}') {
                if (isset($actual[$key])) {
                    unset($expected[$key]);
                    unset($actual[$key]);
                }

                continue;
            }

            if ($value == '{string}') {
                $checkActualValue = isset($actual[$key]) && is_string($actual[$key]);
                if ($checkActualValue) {
                    unset($expected[$key]);
                    unset($actual[$key]);
                }

                continue;
            }

            if ($value == '{integer}') {
                $checkActualValue = isset($actual[$key]) && is_numeric($actual[$key]);
                if ($checkActualValue) {
                    unset($expected[$key]);
                    unset($actual[$key]);
                }

                continue;
            }

            if ($value == '{array}') {
                $checkActualValue = isset($actual[$key]) && is_array($actual[$key]);
                if ($checkActualValue) {
                    unset($expected[$key]);
                    unset($actual[$key]);
                }

                continue;
            }

            if ($value == '{date}') {
                $checkActualValue = isset($actual[$key]) && !empty($actual[$key]) && new \DateTime($actual[$key]);

                if ($checkActualValue) {
                    unset($expected[$key]);
                    unset($actual[$key]);
                }

                continue;
            }
        }
    }

    /**
     * @param array $expected
     * @param array $actual
     */
    private static function clearActualArray(array &$expected, array &$actual)
    {
        foreach ($actual as $key => $value) {
            if (!isset($expected[$key])) {
                // is collection
                $isCollection = is_numeric($key) && is_array($value);
                if (!$isCollection) {
                    unset($actual[$key]);
                }

                continue;
            }

            if (is_array($value) && is_array($expected[$key])) {
                self::clearActualArray($expected[$key], $actual[$key]);
            }
        }
    }

    /**
     * @param int $width
     * @param int $height
     * @return UploadedFile
     */
    protected function getFakeUploadedImage($width = null, $height = null)
    {
        $width  !== null ?: 64;
        $height !== null ?: 64;

        $environment = $this->getContainer()->get('kernel')->getEnvironment();
        $rootDir     = $this->getContainer()->getParameter('kernel.root_dir');
        $filePath    = Image::image($rootDir . '/cache/' . $environment, $width, $height, null);
        $fileName    = basename($filePath);
        $file = new UploadedFile($filePath, $fileName);

        return $file;
    }

    /**
     * @param string $content
     * @return UploadedFile
     */
    protected function getFakeUploadedTxtFile($content = 'Some text')
    {
        $environment = $this->getContainer()->get('kernel')->getEnvironment();
        $rootDir     = $this->getContainer()->getParameter('kernel.root_dir');
        $filePath    = $rootDir . '/cache/' . $environment . '/' . uniqid() . '.txt';

        $result = (bool)file_put_contents($filePath, $content);
        if (!$result) {
            throw new \RuntimeException("Can't create file: $filePath.");
        }

        $fileName = basename($filePath);
        $file = new UploadedFile($filePath, $fileName);

        return $file;
    }

    /**
     * @param string $contentInsideFile
     * @return UploadedFile
     */
    protected function getFakeUploadedPhpFile($contentInsideFile = 'PHP')
    {
        $environment = $this->getContainer()->get('kernel')->getEnvironment();
        $rootDir     = $this->getContainer()->getParameter('kernel.root_dir');
        $filePath    = $rootDir . '/cache/' . $environment . '/' . uniqid() . '.php';
        $content     = '<?php echo "' . $contentInsideFile . '"; ';

        $result = (bool)file_put_contents($filePath, $content);
        if (!$result) {
            throw new \RuntimeException("Can't create file: $filePath.");
        }

        $fileName = basename($filePath);
        $file = new UploadedFile($filePath, $fileName);

        return $file;
    }

    /**
     * @param string $url
     * @param array $data
     */
    protected function queryRequest($url, array $data = [])
    {
        $this->restRequest('GET', $url, $data);
    }

    /**
     * @param string $url
     * @param array $data
     * @param array $files
     */
    protected function createRequest($url, array $data = [], array $files = [])
    {
        $this->restRequest('POST', $url, $data, $files);
    }

    /**
     * @param string $url
     * @param array $data
     * @param array $files
     */
    protected function updateRequest($url, array $data = [], array $files = [])
    {
        $this->restRequest('PUT', $url, $data, $files);
    }

    /**
     * @param string $url
     * @param array $data
     * @param array $files
     */
    protected function patchRequest($url, array $data = [], array $files = [])
    {
        $this->restRequest('PATCH', $url, $data, $files);
    }

    /**
     * @param string $url
     * @param array $data
     */
    protected function deleteRequest($url, array $data = [])
    {
        $this->restRequest('DELETE', $url, $data);
    }

    /**
     * @param string $url
     * @param array $data
     */
    protected function linkRequest($url, array $data = [])
    {
        $this->restRequest('LINK', $url, $data);
    }

    /**
     * @param string $url
     * @param array $data
     */
    protected function unlinkRequest($url, array $data = [])
    {
        $this->restRequest('UNLINK', $url, $data);
    }

    /**
     * @param string $method
     * @param string $url
     * @param array $data
     * @param array $files
     */
    protected function restRequest($method, $url, array $data, array $files = [])
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
     * Takes a URI and converts it to absolute if it is not already absolute.
     *
     * @param string $uri A URI
     * @return string An absolute URI
     */
    protected function getAbsoluteUri($uri)
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
     * @param $url
     * @return mixed
     */
    protected function getFileContent($url)
    {
        $url = $this->getAbsoluteUri($url);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_URL, $url);

        $response = curl_exec($ch);
        if (!$response) {
            throw new \RuntimeException("Resource \"$url\" not found.");
        }

        $actualContent = curl_exec($ch);
        curl_close($ch);

        return $actualContent;
    }

    /**
     * @param $url
     * @return mixed
     */
    protected function getFileContentType($url)
    {
        $url = $this->getAbsoluteUri($url);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_URL, $url);

        $response = curl_exec($ch);
        if (!$response) {
            throw new \RuntimeException("Resource \"$url\" not found.");
        }

        $actualContentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        return $actualContentType;
    }

    /**
     * @param Client $client
     * @return mixed
     */
    protected function getContentAsJson(Client $client)
    {
        $content = $client->getResponse()->getContent();
        $contentData = json_decode($content, true);

        return $contentData;
    }

    /**
     * @param string $url
     * @param string $expectedContentType
     * @param array  $thumbnails
     */
    protected function assertContentTypeUploadedFile($url, $expectedContentType, array $thumbnails = [])
    {
        $actualContentType = $this->getFileContentType($url);
        $this->assertEquals($expectedContentType, $actualContentType);

        foreach ($thumbnails as $thumbnailUrl) {
            $this->assertContentTypeUploadedFile($thumbnailUrl, $expectedContentType);
        }
    }
}
