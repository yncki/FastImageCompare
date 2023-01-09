<?php
/**
 * Created by PhpStorm.
 * User: pepe
 * Date: 24.02.2018
 * Time: 16:06
 */

use pepeEpe\FastImageCompare\FastImageCompare;
use PHPUnit\Framework\TestCase;

class FastImageCompareTest extends TestCase
{

    public function testGetImageSize()
    {
        $ff = new FastImageCompare();
        $size = $ff->getImageSize(__DIR__ . '/images/t00/test1.png');
        $this->assertTrue($size['width'] == 128);
    }

    public function testAreSimilar()
    {
        $ff = new FastImageCompare();
        $files = [
            __DIR__ . '/images/t00/test1.png',
            __DIR__ . '/images/t00/test1-copy.png'
        ];

        $result = $ff->areSimilar($files[0], $files[1], 0.05);
        $this->assertTrue($result);
    }

    public function testAreDifferent()
    {
        $ff = new FastImageCompare();
        $files = [
            __DIR__ . '/images/t00/test1.png',
            __DIR__ . '/images/t00/test2.png'
        ];

        $result = $ff->areDifferent($files[0], $files[1]);
        $this->assertTrue($result);
    }

}
