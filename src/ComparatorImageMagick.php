<?php
/**
 * (c) Paweł Plewa <pawel.plewa@gmail.com> 2018
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 *
 */

namespace pepeEpe\FastImageCompare;

class ComparatorImageMagick extends ComparableBase
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
     * normalized cross correlation (1 = similar)
     */
    const METRIC_NCC = -5;

    /**
     * @see \Imagick::METRIC_* constants
     * @var int
     */
    private $metric;

    /**
     * Creates a ImageMagick comparator instance with default metric METRIC_MAE
     * @param int $metric
     * @param INormalizable[] $normalizers
     */
    public function __construct($metric = self::METRIC_MAE,$normalizers = null)
    {
        parent::__construct();
        $this->setMetric($metric);

        if (is_null($normalizers)){
            $this->registerNormalizer(new NormalizerSquaredSize(8));
        } elseif (is_array($normalizers)){
            $this->setNormalizers($normalizers);
        } elseif ($normalizers instanceof INormalizable){
            $this->registerNormalizer($normalizers);
        }

    }

    /**
     * @param $imageLeftNormalized string
     * @param $imageRightNormalized string
     * @param $imageLeftOriginal string
     * @param $imageRightOriginal string
     * @param $enoughDifference float
     * @param $instance FastImageCompare
     * @return float percentage difference in range 0..1
     */
    public function calculateDifference($imageLeftNormalized, $imageRightNormalized, $imageLeftOriginal, $imageRightOriginal, $enoughDifference,FastImageCompare $instance)
    {
        $imageInstanceLeft = new \imagick();
        $imageInstanceRight = new \imagick();

        //must be set before readImage
        //fuzz is only used for AE metric but we set it always for caching purposes
        $imageInstanceLeft->SetOption('fuzz', (int)($enoughDifference * 100) . '%'); //http://www.imagemagick.org/script/command-line-options.php#define

        $imageInstanceLeft->readImage($imageLeftNormalized);
        $imageInstanceRight->readImage($imageRightNormalized);

        $difference = $imageInstanceLeft->compareImages($imageInstanceRight, $this->localMetricToImagickMetric($this->getMetric()))[1];

        switch ($this->getMetric()) {
//            case self::METRIC_MAE:
            case self::METRIC_AE:
                $difference = ($difference > 0) ? $difference / ($imageInstanceLeft->getImageWidth() * $imageInstanceLeft->getImageHeight()) : $difference;
                break;
            case self::METRIC_NCC:
                $difference = 1.0 - $difference;
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
        $this->metric = $metric;
//        switch ($metric) {
//            case self::METRIC_AE:
//                $this->metric = \Imagick::METRIC_ABSOLUTEERRORMETRIC;
//                break;
//            case self::METRIC_MAE:
//                $this->metric = \Imagick::METRIC_MEANABSOLUTEERROR;
//                break;
//            case self::METRIC_MSE:
//                $this->metric = \Imagick::METRIC_MEANSQUAREERROR;
//                break;
//            case self::METRIC_RMSE:
//                $this->metric = \Imagick::METRIC_ROOTMEANSQUAREDERROR;
//                break;
//            case self::METRIC_NCC:
//                $this->metric = \Imagick::METRIC_NORMALIZEDCROSSCORRELATIONERRORMETRIC;
//                break;
//            default:
//                $this->metric = \Imagick::METRIC_MEANABSOLUTEERROR;
//        }
    }

    private function localMetricToImagickMetric($metric){
        switch ($metric) {
            case self::METRIC_AE:
                return \Imagick::METRIC_ABSOLUTEERRORMETRIC;
                break;
            case self::METRIC_MAE:
                return \Imagick::METRIC_MEANABSOLUTEERROR;
                break;
            case self::METRIC_MSE:
                return \Imagick::METRIC_MEANSQUAREERROR;
                break;
            case self::METRIC_RMSE:
                return \Imagick::METRIC_ROOTMEANSQUAREDERROR;
                break;
            case self::METRIC_NCC:
                return \Imagick::METRIC_NORMALIZEDCROSSCORRELATIONERRORMETRIC;
                break;
            default:
                return \Imagick::METRIC_MEANABSOLUTEERROR;
        }
    }

    public function generateCacheKey($imageLeft,$imageRight)
    {
        return $this->getMetric();
    }

}