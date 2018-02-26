<?php

namespace pepeEpe\FastImageCompare;


interface IClassificable
{

    /**
     * @param $inputFile
     * @param $instance FastImageCompare
     * @return string[] group ids
     */
    public function classify($inputFile, FastImageCompare $instance);

    /**
     * @param $imagePath
     * @return string
     */
    public function generateCacheKey($imagePath);
}