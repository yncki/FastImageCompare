<?php

namespace pepeEpe\FastImageCompare;

class ClassifierFileExtension implements IClassificable
{
    /**
     * @param $inputFile
     * @return string[] group ids
     */
    public function classify($inputFile)
    {
        $ext = pathinfo($inputFile, PATHINFO_EXTENSION);
        return ['extension:' . $ext];
    }

}