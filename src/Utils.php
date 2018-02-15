<?php
/**
 * (c) Paweł Plewa <pawel.plewa@gmail.com> 2018
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 *
 */

namespace pepeEpe\FastImageCompare;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class Utils
{

    /**
     * @param $path
     * @param null $notLastModifiedSecondsAgo
     * @return array
     */
    public static function getFilesOlderBy($path, $notLastModifiedSecondsAgo = null)
    {
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
        $files = array();
        foreach ($rii as $file)
            /**
             * @var $file SplFileInfo
             */
            if (!$file->isDir()) {
                if (!is_null($notLastModifiedSecondsAgo)) {
                    $nDate = new \DateTime();
                    $fDate = \DateTime::createFromFormat('U', $file->getMTime());
                    if ($notLastModifiedSecondsAgo <= $nDate->getTimestamp()-$fDate->getTimestamp()) {
                        $files[] = $file->getPathname();
                    }
                } else {
                    $files[] = $file->getPathname();
                }
            }
        return array_unique($files);
    }

    public static function getFilesIn($path)
    {
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
        $files = array();
        foreach ($rii as $file)
            /**
             * @var $file SplFileInfo
             */
            if (!$file->isDir()) {
                $files[] = $file->getPathname();
            }
        return array_unique($files);
    }

    /**
     * @param $classOrObject
     * @return string
     */
    public static function getClassNameWithoutNamespace($classOrObject) {
        if (is_object($classOrObject)) $classOrObject = get_class($classOrObject);
        $path = explode('\\', ($classOrObject));
        return array_pop($path);
    }

    /**
     * @param $filesArray
     */
    public static function removeFiles($filesArray){
        foreach ($filesArray as $file){
            @unlink($file);
        }
    }

    /**
     * @see https://stackoverflow.com/a/8260942
     * @param $url
     * @return string
     */
    public static function normalizeUrl($url)
    {
        $parts = parse_url($url);
        $path_parts = array_map('rawurldecode', explode('/', $parts['path']));
        return
            $parts['scheme'] . '://' .
            $parts['host'] .
            implode('/', array_map('rawurlencode', $path_parts));
    }
}