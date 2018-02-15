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

//    /**
//     * Equalize ( aka normalize ) images before comparison ,
//     * @var bool
//     */
//    private $equalize = true;


    /**
     * Creates a ImageMagick comparator instance with default metric METRIC_MAE
     * @param int $metric
     * @param INormalizer[] $normalizers
     */
    public function __construct($metric = self::METRIC_MAE,$normalizers = null)
    {
        $this->setMetric($metric);

        if (is_null($normalizers)){
            $this->registerNormalizer(new NormalizerSizeType(8));
        } elseif (is_array($normalizers)){
            $this->setNormalizers($normalizers);
        } elseif ($normalizers instanceof INormalizer){
            $this->registerNormalizer($normalizers);
        }

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
        //fuzz is only used for AE metric but we set it always for caching purposes
        $imageInstanceLeft->SetOption('fuzz', (int)($enoughDifference * 100) . '%'); //http://www.imagemagick.org/script/command-line-options.php#define

        $imageInstanceLeft->readImage($imageLeftNormalized);
        $imageInstanceRight->readImage($imageRightNormalized);

        $difference = $imageInstanceLeft->compareImages($imageInstanceRight, $this->getMetric());
//        $difference = $imageInstanceLeft->compareImages($imageInstanceRight, \Imagick::METRIC_NORMALIZEDCROSSCORRELATIONERRORMETRIC);
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
            case self::METRIC_NCC:
                $this->metric = \Imagick::METRIC_NORMALIZEDCROSSCORRELATIONERRORMETRIC;
                break;
            default:
                $this->metric = \Imagick::METRIC_MEANABSOLUTEERROR;
        }
    }

//    /**
//     * @return bool
//     */
//    public function isEqualize()
//    {
//        return $this->equalize;
//    }
//
//    /**
//     * @param bool $equalize
//     */
//    public function setEqualize($equalize)
//    {
//        $this->equalize = $equalize;
//    }



}