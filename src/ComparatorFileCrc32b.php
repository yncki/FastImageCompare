<?php
/**
 * (c) Paweł Plewa <pawel.plewa@gmail.com> 2018
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 *
 */

namespace pepeEpe\FastImageCompare;
use Psr\Log\InvalidArgumentException;
use Symfony\Component\Cache\Adapter\AdapterInterface;

/**
 * Class ComparatorCrc32b
 * @package pepeEpe\FastImageCompare
 *
 * Compares crc32b of two files, return 1.0 or 0.0 ( 100% diff when not equal or 0% diff when equal )
 */
class ComparatorFileCrc32b extends ComparableBase
{
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
        $left   = $this->cachedHash($imageLeftOriginal,$instance->getCacheAdapter());
        $right  = $this->cachedHash($imageRightOriginal,$instance->getCacheAdapter());

        return ($left === $right) ? 0.0 : 1.0;
    }

    /**
     * @param $filePath
     * @param AdapterInterface $cacheAdapter
     * @return string
     */
    private function cachedHash($filePath, $cacheAdapter){
        $key = Utils::getClassNameWithoutNamespace($this).'.'.md5($filePath);
        $item = $cacheAdapter->getItem($key);
        if ($item->isHit()){
            return $item->get();
        } else {
            $result = hash('crc32b',file_get_contents($filePath));
            $item->set($result);
            $cacheAdapter->save($item);
            return $result;
        }
    }

    public function generateCacheKey($imageLeft, $imageRight)
    {
        return '';
    }

}