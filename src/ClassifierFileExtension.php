<?php

namespace pepeEpe\FastImageCompare;

class ClassifierFileExtension extends ClassificableBase
{
    /**
     * @param $inputFile
     * @param $instance FastImageCompare
     * @return string[]
     */
    protected function internalClassify($inputFile, FastImageCompare $instance)
    {
        $ext = pathinfo($inputFile, PATHINFO_EXTENSION);
        return ['extension:' . $ext];
    }

    /**
     * @param $imagePath
     * @return string
     */
    public function generateCacheKey($imagePath)
    {
        return '';
    }


}