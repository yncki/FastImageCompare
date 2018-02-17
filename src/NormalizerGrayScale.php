<?php
/**
 * (c) Paweł Plewa <pawel.plewa@gmail.com> 2018
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 *
 */

namespace pepeEpe\FastImageCompare;

class NormalizerGrayScale extends NormalizableBase {
    /**
     * @param $imagePath
     * @param $result
     * @param $tempDir
     * @return string path
     */
    public function normalize($imagePath,$result, $tempDir)
    {
        $imageInstanceLeft = new \imagick();
        $imageInstanceLeft->readImage($imagePath);
        $imageInstanceLeft->transformimagecolorspace(\Imagick::COLORSPACE_GRAY);
        $imageInstanceLeft->setColorspace(\Imagick::COLORSPACE_GRAY);
        $imageInstanceLeft->writeImage($result);
        $imageInstanceLeft->clear();
        unset($imageInstanceLeft);
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