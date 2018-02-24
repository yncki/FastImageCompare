<?php

interface ISegmentable {

    /**
     * @param $inputFile
     * @return string[] group ids
     */
    public function segmentable($inputFile);
}