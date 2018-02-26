<?php

namespace pepeEpe\FastImageCompare;

class ClassifierColor implements IClassificable
{

    const MAX_COLORS_TO_SCAN = 256;
    /**
     * @var int
     */
    private $precision = 10;


    /**
     * @param $inputFile
     * @return array|string[]
     */
    public function classify($inputFile)
    {
        //assume it is grayscale
        $isGrayScale = true;
        try {
            // Max colors to scan
            $ff = new \imagick($inputFile);
            $count = [];
            $uniqueColors = 0;
            for ($x = 0; $x < $ff->getImageWidth(); $x += $this->precision) {
                for ($y = 0; $y < $ff->getImageHeight(); $y += $this->precision) {
                    $color = $ff->getImagePixelColor($x, $y)->getColor();
                    $key = $color['r'] . '-' . $color['g'] . '-' . $color['b'];
                    if ($isGrayScale)
                        if ($color['r'] != $color['g'] && $color['g'] != $color['b']) $isGrayScale = false;

                    $count[$key]++;
                    $uniqueColors = count(array_keys($count));
                    if ($uniqueColors >= self::MAX_COLORS_TO_SCAN + 1) break 2;
                }
            }
            $ff->clear();
            unset($ff);
            if ($uniqueColors <= 16)
                return ['colors:<=16', $isGrayScale ? 'colors:grayscale' : 'colors:color'];
            if ($uniqueColors <= 64)
                return ['colors:<=64', $isGrayScale ? 'colors:grayscale' : 'colors:color'];
            if ($uniqueColors <= 128)
                return ['colors:<=128', $isGrayScale ? 'colors:grayscale' : 'colors:color'];
            if ($uniqueColors <= 256)
                return ['colors:<=256', $isGrayScale ? 'colors:grayscale' : 'colors:color'];
            return ['colors:>256', $isGrayScale ? 'colors:grayscale' : 'colors:color'];
        } catch (\Exception $e) {

        }
        return [];
    }

}