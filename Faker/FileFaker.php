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
     * @var string
     */
    private $kernelRootDir;

    /**
     * FileFaker constructor.
     * @param KernelInterface $kernel
     * @param string          $kernelRootDir
     */
    public function __construct(KernelInterface $kernel, $kernelRootDir)
    {
        $this->kernel        = $kernel;
        $this->kernelRootDir = $kernelRootDir;
    }

    /**
     * @param int $width
     * @param int $height
     * @return UploadedFile
     */
    public function getFakeUploadedImage($width = null, $height = null)
    {
        $width  !== null ?: 64;
        $height !== null ?: 64;

        $filePath = Image::image($this->getCacheDir(), $width, $height, null);
        $fileName = basename($filePath);
        $file = new UploadedFile($filePath, $fileName);

        return $file;
    }

    /**
     * @param string $content
     * @return UploadedFile
     */
    public function getFakeUploadedTxtFile($content = 'Some text')
    {
        $filePath    = $this->getCacheDir() . '/' . uniqid() . '.txt';

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
    public function getFakeUploadedPhpFile($contentInsideFile = 'PHP')
    {
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
        $environment = $this->kernel->getEnvironment();
        $rootDir     = $this->kernelRootDir;

        return $rootDir . '/cache/' . $environment;
    }
}