<?php
/**
 * (c) Paweł Plewa <pawel.plewa@gmail.com> 2018
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 *
 */

namespace pepeEpe\FastImageCompare;

interface INormalizable {


    /**
     * @param $imagePath
     * @param $tempDir
     * @param $normalizedPipeline
     * @return mixed
     */
    public function process($imagePath,$tempDir,&$normalizedPipeline);

}