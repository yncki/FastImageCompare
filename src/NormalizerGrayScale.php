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
     * @param $inputImagePath
     * @param $outputImagePath
     * @param $tempDir
     * @return string path
     */
    public function normalize($inputImagePath, $outputImagePath, $tempDir)
    {
        $imageInstanceLeft = new \imagick();
        $imageInstanceLeft->readImage($inputImagePath);
        $imageInstanceLeft->transformimagecolorspace(\Imagick::COLORSPACE_GRAY);
        $imageInstanceLeft->setColorspace(\Imagick::COLORSPACE_GRAY);
        $imageInstanceLeft->writeImage($outputImagePath);
        $imageInstanceLeft->clear();
        unset($imageInstanceLeft);
        return $outputImagePath;
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