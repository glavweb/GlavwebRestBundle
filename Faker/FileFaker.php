<?php

namespace Glavweb\RestBundle\Faker;
use Faker\Provider\Image;
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
        $width  = $width  !== null ? $width : 64;
        $height = $height !== null ? $height : 64;
        $allowedTypes = ['jpeg', 'gif', 'png'];

        if (!in_array($type, $allowedTypes)) {
            throw new \RuntimeException(sprintf('Type %s is not allowed. Type must be one of: %s',
                $type,
                implode(', ', $allowedTypes)
            ));
        }

        $filePath = $this->getCacheDir() . '/' . $fileName;

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
        $content = $content !== null ? $content : 'Some text';

        $filePath = $this->getCacheDir() . '/' . uniqid() . '.txt';

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
    public function getFakeUploadedPhpFile($contentInsideFile = null)
    {
        $contentInsideFile = $contentInsideFile !== null ? $contentInsideFile : 'PHP';

        $filePath = $this->getCacheDir() . '/' . uniqid() . '.php';
        $content  = '<?php echo "' . $contentInsideFile . '"; ';

        $result = (bool)file_put_contents($filePath, $content);
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