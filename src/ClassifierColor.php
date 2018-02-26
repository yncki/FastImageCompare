<?php

namespace pepeEpe\FastImageCompare;

class ClassifierColor extends ClassificableBase
{

    const MAX_COLORS_TO_SCAN = 256;
    /**
     * @var int
     */
    private $precision = 10;

    const COLORS_GRAYSCALE = 'colors:grayscale';
    const COLORS_COLOR = 'colors:color';

    const C_COUNT_BELOW_16 = 'colorCount:<=16';
    const C_COUNT_BELOW_64 = 'colorCount:<=64';
    const C_COUNT_BELOW_128 = 'colorCount:<=128';
    const C_COUNT_BELOW_256 = 'colorCount:<=256';
    const C_COUNT_ABOVE_256 = 'colorCount:>256';

    /**
     * @param $inputFile
     * @param $instance FastImageCompare
     * @return string[]
     */
    protected function internalClassify($inputFile, FastImageCompare $instance)
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
                return [
                    self::C_COUNT_BELOW_16,
                    $isGrayScale ? self::COLORS_GRAYSCALE : self::COLORS_COLOR
                ];
            if ($uniqueColors <= 64)
                return [
                    self::C_COUNT_BELOW_64,
                    $isGrayScale ? self::COLORS_GRAYSCALE : self::COLORS_COLOR
                ];
            if ($uniqueColors <= 128)
                return [
                    self::C_COUNT_BELOW_128,
                    $isGrayScale ? self::COLORS_GRAYSCALE : self::COLORS_COLOR
                ];
            if ($uniqueColors <= 256)
                return [
                    self::C_COUNT_BELOW_256,
                    $isGrayScale ? self::COLORS_GRAYSCALE : self::COLORS_COLOR
                ];
            return [
                self::C_COUNT_ABOVE_256,
                $isGrayScale ? self::COLORS_GRAYSCALE : self::COLORS_COLOR
            ];
        } catch (\Exception $e) {

        }
        return [];
    }

    /**
     * @param $imagePath
     * @return string
     */
    public function generateCacheKey($imagePath)
    {
        return implode('-', array(self::MAX_COLORS_TO_SCAN, $this->precision));
    }


}