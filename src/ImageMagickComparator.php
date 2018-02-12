<?php
/**
 * (c) Paweł Plewa <pawel.plewa@gmail.com> 2018
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 *
 */

namespace pepeEpe\FastImageCompare;

class ImageMagickComparator implements IImageComparator {


    const METRIC_AE         = -1;
    const METRIC_MAE        = -2;
    const METRIC_MSE        = -3;
    const METRIC_RMSE       = -4;

    /**
     * @see \Imagick::METRIC_* constants
     * @var int
     */
    private $metric;


    /**
     * Creates a ImageMagick comparator instance with default metric = MEAN ABSOLUTE ERROR
     * @see \Imagick::METRIC_* constants for more details
     * @param int $metric
     */
    public function __construct($metric = self::METRIC_MAE)
    {
        $this->setMetric($metric);
    }

    /**
     * @param string $imageLeft
     * @param string $imageRight
     * @param float $enoughDifference
     * @return float percentage difference in range 0..1
     */
    public function calculateDifference($imageLeft, $imageRight, $enoughDifference)
    {

        $imageInstanceLeft = new \imagick();
        $imageInstanceRight = new \imagick();

        //must be set before readImage
        //if ($this->getMetric() == \Imagick::METRIC_ABSOLUTEERRORMETRIC) {
        //fuzz is only used for AE metric
        $imageInstanceLeft->SetOption('fuzz', (int)($enoughDifference * 100) . '%'); //http://www.imagemagick.org/script/command-line-options.php#define
        //}

        $imageInstanceLeft->readImage($imageLeft);
        $imageInstanceRight->readImage($imageRight);

        $difference = $imageInstanceLeft->compareImages($imageInstanceRight, $this->getMetric());
        $difference = $difference[1];

        switch ($this->getMetric()){
            case \Imagick::METRIC_ABSOLUTEERRORMETRIC:
                $difference = ($difference > 0) ? $difference / ($imageInstanceLeft->getImageWidth() * $imageInstanceLeft->getImageHeight()):$difference;
                break;
        }

        $imageInstanceLeft->clear();
        $imageInstanceRight->clear();
        unset($imageInstanceLeft);
        unset($imageInstanceRight);
        return $difference;
    }


    /**
     * @return int
     */
    public function getMetric()
    {
        return $this->metric;
    }

    /**
     * @see \Imagick::METRIC_*
     * @param int $metric
     */
    public function setMetric($metric)
    {
        switch ($metric){
            case self::METRIC_AE:
                $this->metric = \Imagick::METRIC_ABSOLUTEERRORMETRIC;
                break;
            case self::METRIC_MAE:
                $this->metric = \Imagick::METRIC_MEANABSOLUTEERROR;
                break;
            case self::METRIC_MSE:
                $this->metric = \Imagick::METRIC_MEANSQUAREERROR;
                break;
            case self::METRIC_RMSE:
                $this->metric = \Imagick::METRIC_ROOTMEANSQUAREDERROR;
                break;
        }
    }
}