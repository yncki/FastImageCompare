<?php
/**
 * (c) Paweł Plewa <pawel.plewa@gmail.com> 2018
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 *
 */

namespace pepeEpe\FastImageCompare;

class NormalizerHistogram extends NormalizerBase {
    /**
     * @param $imagePath
     * @param $tempDir
     * @return string path
     */
    public function normalize($imagePath, $tempDir)
    {
        $result = $imagePath;
        if (file_exists($imagePath)) {
            $cacheFileName = $this->getCachedFile($imagePath,$tempDir);
            if (!file_exists($cacheFileName)) {
                $imageInstanceLeft = new \imagick();
                $imageInstanceLeft->readImage($imagePath);
                $imageInstanceLeft->normalizeImage();
                $imageInstanceLeft->writeImage($cacheFileName);
                $imageInstanceLeft->clear();
                unset($imageInstanceLeft);
            }
            return $cacheFileName;
        }
        return $result;


    }

    /**
     * @param $imagePath
     * @return string
     */
    public function getCacheKey($imagePath)
    {
        return md5($imagePath);
    }

}