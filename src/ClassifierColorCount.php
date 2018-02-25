<?php

namespace pepeEpe\FastImageCompare;

class ClassifierColorCount implements IClassificable
{


    private $precision = 10;


    /**
     * @param $inputFile
     * @return array|string[]
     */
    public function classify($inputFile)
    {
        try {
            // Max colors to scan
            $MAX = 256;
            $ff = new \imagick($inputFile);
            $count = [];
            $uniqueColors = 0;
            for ($x = 0; $x < $ff->getImageWidth(); $x += $this->precision) {
                for ($y = 0; $y < $ff->getImageHeight(); $y += $this->precision) {
                    $color = $ff->getImagePixelColor($x, $y)->getColor();
                    $key = $color['r'] . '-' . $color['g'] . '-' . $color['b'];
                    $count[$key]++;
                    $uniqueColors = count(array_keys($count));
                    if ($uniqueColors >= $MAX + 1) break 2;
                }
            }
            $ff->clear();
            unset($ff);
            if ($uniqueColors <= 16)
                return ['colors:<=16'];
            if ($uniqueColors <= 256)
                return ['colors:<=256'];
            return ['colors:>256'];
        } catch (\Exception $e) {

        }
        return [];
    }

}