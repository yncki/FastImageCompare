<?php

namespace pepeEpe\FastImageCompare;


interface IClassificable
{

    /**
     * @param $inputFile
     * @return string[] group ids
     */
    public function classify($inputFile);
}