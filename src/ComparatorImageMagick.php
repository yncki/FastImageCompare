<?php
/**
 * (c) Paweł Plewa <pawel.plewa@gmail.com> 2018
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 *
 */

namespace pepeEpe\FastImageCompare;

class ComparatorImageMagick implements IComparable
{

    /**
     * Absolute Error count of the number of different pixels (0=equal)
     */
    const METRIC_AE = -1;

    /**
     * Mean absolute error    (average channel error distance)
     */
    const METRIC_MAE = -2;

    /**
     * Mean squared error     (averaged squared error distance)
     */
    const METRIC_MSE = -3;

    /**
     * (sq)root mean squared error -- IE:  sqrt(MSE)
     */
    const METRIC_RMSE = -4;

    /**
     * @see \Imagick::METRIC_* constants
     * @var int
     */
    private $metric;


    /**
     * Creates a ImageMagick comparator instance with default metric METRIC_MAE
     * @param int $metric
     */
    public function __construct($metric = self::METRIC_MAE)
    {
        $this->setMetric($metric);
    }

    /**
     * @param $imageLeftNormalized string
     * @param $imageRightNormalized string
     * @param $imageLeftOriginal string
     * @param $imageRightOriginal string
     * @param $enoughDifference float
     * @return float percentage difference in range 0..1
     */
    public function calculateDifference($imageLeftNormalized, $imageRightNormalized, $imageLeftOriginal, $imageRightOriginal, $enoughDifference)
    {
        $imageInstanceLeft = new \imagick();
        $imageInstanceRight = new \imagick();

        //must be set before readImage
        //if ($this->getMetric() == \Imagick::METRIC_ABSOLUTEERRORMETRIC) {
        //fuzz is only used for AE metric but we set it always for caching purposes
        $imageInstanceLeft->SetOption('fuzz', (int)($enoughDifference * 100) . '%'); //http://www.imagemagick.org/script/command-line-options.php#define

        $imageInstanceLeft->readImage($imageLeftNormalized);
        $imageInstanceRight->readImage($imageRightNormalized);

        $difference = $imageInstanceLeft->compareImages($imageInstanceRight, $this->getMetric());
        $difference = $difference[1];

        switch ($this->getMetric()) {
            case \Imagick::METRIC_ABSOLUTEERRORMETRIC:
                $difference = ($difference > 0) ? $difference / ($imageInstanceLeft->getImageWidth() * $imageInstanceLeft->getImageHeight()) : $difference;
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
        switch ($metric) {
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
            default:
                $this->metric = \Imagick::METRIC_MEANABSOLUTEERROR;
        }
    }
}