<?php
/**
 * (c) Paweł Plewa <pawel.plewa@gmail.com> 2018
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 *
 */

namespace pepeEpe\FastImageCompare;

class NormalizerHistogram extends NormalizableBase {
    /**
     * @param $imagePath
     * @param $output
     * @param $tempDir
     * @return string path
     */
    public function normalize($imagePath, $output, $tempDir)
    {
        $imageInstanceLeft = new \imagick();
        $imageInstanceLeft->readImage($imagePath);
        $imageInstanceLeft->normalizeImage();
        $imageInstanceLeft->writeImage($output);
        $imageInstanceLeft->clear();
        return $output;
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