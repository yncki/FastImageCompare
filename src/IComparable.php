<?php
/**
 * (c) Paweł Plewa <pawel.plewa@gmail.com> 2018
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 *
 */

namespace pepeEpe\FastImageCompare;

interface IComparable {

    const EXCLUDE     = 0;
    const PASSTHROUGH = 1;

    /**
     * @param $imageLeftNormalized string
     * @param $imageRightNormalized string
     * @param $imageLeftOriginal string
     * @param $imageRightOriginal string
     * @param $enoughDifference float
     * @return float in range 0..1
     */
    public function calculateDifference($imageLeftNormalized, $imageRightNormalized,$imageLeftOriginal,$imageRightOriginal, $enoughDifference);

    /**
     * @param $inputLeft
     * @param $inputRight
     * @param $enoughDifference
     * @param $instance FastImageCompare
     * @return float
     */
    public function difference($inputLeft,$inputRight,$enoughDifference,FastImageCompare $instance);


    /**
     * @return int
     */
    public function getComparableMode();

    /**
     * @param int $comparableMode
     */
    public function setComparableMode($comparableMode);


    public function registerNormalizer(INormalizer $normalizerInstance);

    /**
     * @param INormalizer[] $normalizerInstances
     */
    public function setNormalizers(array $normalizerInstances);

    /**
     * @return INormalizer[]
     */
    public function getNormalizers();

    /**
     * Clear normalizers
     */
    public function clearNormalizers();

}