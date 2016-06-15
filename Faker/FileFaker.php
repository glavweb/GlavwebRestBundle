<?php

namespace Glavweb\RestBundle\Faker;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Class FileFaker
 * @package Glavweb\RestBundle\Faker
 */
class FileFaker
{
    /**
     * @var KernelInterface
     */
    private $kernel;


    /**
     * @param string $type
     * @param string $filePath
     * @param int    $width
     * @param int    $height
     * @return bool
     */
    public static function fakeImage($type, $filePath, $width = null, $height = null)
    {
        $width  = $width  !== null ? $width : 64;
        $height = $height !== null ? $height : 64;
        $allowedTypes = ['jpeg', 'gif', 'png'];

        if (!in_array($type, $allowedTypes)) {
            throw new \RuntimeException(sprintf('Type %s is not allowed. Type must be one of: %s',
                $type,
                implode(', ', $allowedTypes)
            ));
        }

        $resource = imagecreate($width, $height);

        $result = false;
        switch ($type) {
            case 'jpeg':
                $result = imagejpeg($resource, $filePath);

                break;

            case 'gif':
                $result = imagejpeg($resource, $filePath);

                break;

            case 'png':
                $result = imagejpeg($resource, $filePath);

                break;
        }

        return $result;
    }

    /**
     * @param string $filePath
     * @param string $content
     * @return bool
     */
    public static function fakePhpFile($filePath, $content = null)
    {
        $content = $content !== null ? $content : 'PHP';

        return (bool)file_put_contents($filePath, '<?php echo "' . $content . '"; ');
    }

    /**
     * @param string $filePath
     * @param string $content
     * @return bool
     */
    public function fakeTxtFile($filePath, $content = null)
    {
        $content = $content !== null ? $content : 'Some text';

        return (bool)file_put_contents($filePath, $content);
    }

    /**
     * FileFaker constructor.
     * @param KernelInterface $kernel
     */
    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    /**
     * @param string $fileName
     * @param int    $width
     * @param int    $height
     * @return UploadedFile
     */
    public function getFakeUploadedImageJpeg($fileName, $width = null, $height = null)
    {
        return $this->getFakeUploadedImage('jpeg', $fileName, $width, $height);
    }

    /**
     * @param string $fileName
     * @param int    $width
     * @param int    $height
     * @return UploadedFile
     */
    public function getFakeUploadedImageGif($fileName, $width = null, $height = null)
    {
        return $this->getFakeUploadedImage('gif', $fileName, $width, $height);
    }

    /**
     * @param string $fileName
     * @param int    $width
     * @param int    $height
     * @return UploadedFile
     */
    public function getFakeUploadedImagePng($fileName, $width = null, $height = null)
    {
        return $this->getFakeUploadedImage('png', $fileName, $width, $height);
    }

    /**
     * @param string $type
     * @param string $fileName
     * @param int    $width
     * @param int    $height
     * @return UploadedFile
     */
    public function getFakeUploadedImage($type, $fileName, $width = null, $height = null)
    {
        $filePath = $this->getCacheDir() . '/' . $fileName;

        $result = self::fakeImage($type, $filePath, $width, $height);
        if (!$result) {
            throw new \RuntimeException('Image did not created.');
        }

        $file = new UploadedFile($filePath, $fileName);

        return $file;
    }

    /**
     * @param string $content
     * @return UploadedFile
     */
    public function getFakeUploadedTxtFile($content = null)
    {
        $filePath = $this->getCacheDir() . '/' . uniqid() . '.txt';

        $result = self::fakeTxtFile($filePath, $content);
        if (!$result) {
            throw new \RuntimeException("Can't create file: $filePath.");
        }

        $fileName = basename($filePath);
        $file = new UploadedFile($filePath, $fileName);

        return $file;
    }

    /**
     * @param string $content
     * @return UploadedFile
     */
    public function getFakeUploadedPhpFile($content = null)
    {
        $filePath = $this->getCacheDir() . '/' . uniqid() . '.php';

        $result = self::fakePhpFile($filePath, $content);

        if (!$result) {
            throw new \RuntimeException("Can't create file: $filePath.");
        }

        $fileName = basename($filePath);
        $file = new UploadedFile($filePath, $fileName);

        return $file;
    }

    /**
     * @return string
     */
    private function getCacheDir()
    {
        $cacheDir = $this->kernel->getCacheDir() . '/' . 'faker';

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir);
        }

        return $cacheDir;
    }
}