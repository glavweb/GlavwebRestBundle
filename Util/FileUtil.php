<?php

namespace Glavweb\RestBundle\Util;

/**
 * Class FileUtil
 * @package Glavweb\RestBundle\Util
 */
class FileUtil
{
    /**
     * @param $url
     * @return mixed
     */
    public static function getFileContentType($url)
    {
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
     * @param $url
     * @return mixed
     */
    public static function getFileContent($url)
    {
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
}