<?php
/**
 * (c) Paweł Plewa <pawel.plewa@gmail.com> 2018
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 *
 */

namespace pepeEpe\FastImageCompare;

/**
 * Class ComparatorCrc32b
 * @package pepeEpe\FastImageCompare
 *
 * Compares crc32b of two files, return 1.0 or 0.0 ( 100% diff when not equal or 0% diff when  equal )
 */
class ComparatorFileCrc32b extends ComparableBase
{
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
        return hash('crc32b',file_get_contents($imageLeftOriginal)) === hash('crc32b',file_get_contents($imageRightOriginal)) ? 0.0 : 1.0;
    }
}