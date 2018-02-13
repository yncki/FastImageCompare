<?php
/**
 * (c) Paweł Plewa <pawel.plewa@gmail.com> 2018
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 *
 */

namespace pepeEpe\FastImageCompare;


use Gumlet\ImageResize;

class NormalizerSizeType implements INormalizable
{

    /**
     * @var int sample size to normalize , preffered POW
     */
    private $sampleSize;

    public function __construct($sampleSize = 8)
    {
        $this->setSampleSize($sampleSize);
    }

    public function process($imagePath, $tempDir, &$normalizedPipeline)
    {
        $result = [];
        if (file_exists($imagePath)) {
            $baseName = basename($imagePath);
            $baseNameMd5 = md5($baseName);
            $normalizedKey = '.n.' . $this->getSampleSize();
            $normalizedOutputFileName = $baseNameMd5 . $normalizedKey . '.png';

            if (!file_exists($tempDir.$normalizedOutputFileName)) {
                $imageResize = new ImageResize($imagePath);
                $imageResize->quality_jpg = 100;
                $imageResize->quality_png = 9;
                $imageResize->quality_webp = 100;
                $imageResize->quality_truecolor = true;
                $imageResize->resize($this->getSampleSize(), $this->getSampleSize(), true);
                $imageResize->save($tempDir . $normalizedOutputFileName,IMAGETYPE_PNG);
                unset($imageResize);
            }
            //TODO change key format
            $result[$tempDir . $normalizedOutputFileName] = $imagePath;
        }
        return $result;
    }


    /**
     * @return int
     */
    public function getSampleSize()
    {
        return $this->sampleSize;
    }

    /**
     * @param int $sampleSize
     */
    public function setSampleSize($sampleSize)
    {
        $this->sampleSize = $sampleSize;
    }





}