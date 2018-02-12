<?php
/**
 * (c) Paweł Plewa <pawel.plewa@gmail.com> 2018
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 *
 */

namespace pepeEpe\FastImageCompare;

class ImageCrcComparator implements IImageComparator {

    /**
     * @param $imageLeft string
     * @param $imageRight string
     * @param $enoughDifference float
     * @return float percentage difference in range 0..1
     */
    public function calculateDifference($imageLeft, $imageRight, $enoughDifference)
    {
        return hash('crc32b',file_get_contents($imageLeft)) == hash('crc32b',file_get_contents($imageRight)) ? 0 : 1;
    }

}