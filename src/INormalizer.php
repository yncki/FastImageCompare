<?php
/**
 * (c) Paweł Plewa <pawel.plewa@gmail.com> 2018
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 *
 */

namespace pepeEpe\FastImageCompare;

interface INormalizer {


    /**
     * @param $imagePath
     * @param $tempDir
     * @return string path
     */
    public function normalize($imagePath, $tempDir);

    /**
     * @param $imagePath
     * @return string
     */
    public function getCacheKey($imagePath);


    public function getCachedFile($filePath,$temporaryDirectory);


}