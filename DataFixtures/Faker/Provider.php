<?php

namespace Glavweb\RestBundle\DataFixtures\Faker;

use Glavweb\RestBundle\Faker\FileFaker;

/**
 * Class Provider
 *
 * @package Glavweb\RestBundle
 */
class Provider
{
    /**
     * @param $type
     * @param $location
     * @param $file
     * @param int $width
     * @param int $height
     * @return string
     */
    public static function test_image($type, $location, $file, $width = 64, $height = 64)
    {
        FileFaker::fakeImage($type, trim($location, '/') . '/' . $file, $width, $height);

        return $file;
    }
}