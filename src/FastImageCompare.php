<?php
/**
 * (c) Paweł Plewa <pawel.plewa@gmail.com> 2018
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 *
 */


namespace pepeEpe\FastImageCompare;

use Gumlet\ImageResize;
use FastImageSize\FastImageSize;

/**
 * Class FastImageCompare
 *
 * By default it calculates difference of images using Absolute Error metric ( AE )
 *
 *
 * @package pepeEpe
 */
class FastImageCompare
{

    const PREFER_ANY = 1;
    const PREFER_LARGER_IMAGE = 2;
    const PREFER_LOWER_DIFFERENCE = 4;
    const PREFER_LARGER_DIFFERENCE = 8;

    /**
     * @var int
     */
    protected $fuzzPercentage = 2;

    /**
     * Sample size to normalize before comparing [ width & height ]
     * For most purposes sample size should be 8|16|32
     * For more precise results use > 64 ( slower and more memory hungry )
     *
     * @var int
     */
    protected $sampleSize;

    protected $temporaryDirectory;

    protected $temporaryDirectoryPermissions = 0755;

    private $imageSizerInstance = null;

    public function __construct($temporaryDirectory = null, $sampleSize = 8)
    {
        $this->setTemporaryDirectory($temporaryDirectory);
        $this->setSampleSize($sampleSize);
    }

    public static function debug(array $input)
    {
        $root = $_SERVER['DOCUMENT_ROOT'];
        echo '<hr>';
        foreach ($input as $img) {
            $url = str_replace($root, '', $img);
            echo '<img style="height:100px;padding:4px;" src="' . $url . '"/>';//<br/>';
        }
    }

    /**
     * @return int
     */
    public function getTemporaryDirectoryPermissions()
    {
        return $this->temporaryDirectoryPermissions;
    }

    /**
     * @param int $temporaryDirectoryPermissions
     */
    public function setTemporaryDirectoryPermissions($temporaryDirectoryPermissions)
    {
        $this->temporaryDirectoryPermissions = $temporaryDirectoryPermissions;
    }

    /**
     * @param array $inputImages
     * @param float $enough
     * @return array
     */
    public function extractDuplicates(array $inputImages, $enough = 0.05)
    {
        $output = [];
        $compared = $this->compareArray($inputImages);
        foreach ($compared as $data) {
            if ($data[2] <= $enough) {
                $output[] = $data[0];
                $output[] = $data[1];
            }
        }
        return array_unique($output);
    }

    /**
     * Compares each with each and return difference percentage in range 0..1
     * @param array $inputImages
     * @return array
     */
    public function compareArray(array $inputImages)
    {
        $output = [];
        $normalizedImages = $this->normalizeArray($inputImages);
        $normalizedImagesIndexed = array_keys($normalizedImages);
        $imageNameKeys = array_keys($normalizedImagesIndexed);
        //compare each with each
        for ($x = 0; $x < count($normalizedImagesIndexed) - 1; $x++) {
            for ($y = $x + 1; $y < count($normalizedImagesIndexed); $y++) {
                $imageLeft = $normalizedImagesIndexed[$imageNameKeys[$x]];
                $imageRight = $normalizedImagesIndexed[$imageNameKeys[$y]];
                $compareResult = $this->compareImages($imageLeft, $imageRight);
                $output[] = [$normalizedImages[$imageLeft], $normalizedImages[$imageRight], $compareResult];

            }
        }
        return $output;
    }

    /**
     * Creates normalized [aspectRatio & pixelCount ] version of images from array $images
     * Common resolution is used for all input sampleSize x sampleSize @see FastImageCompare::setSampleSize()
     *
     * @param array $images
     * @return string[] normalized images absolute paths
     * @throws Exception
     */
    protected function normalizeArray(array $images)
    {
        $images = array_unique($images);
        $normalized = [];
        foreach ($images as $imagePath) {
            $normalized= array_merge($normalized,$this->normalize($imagePath));
        }
        return $normalized;
    }


    protected function normalize($imagePath){
        $normalized = [];
        if (file_exists($imagePath)) {
            $baseName = basename($imagePath);
            $baseNameMd5 = md5($baseName);
            $normalizedKey = '.normalized.' . $this->getSampleSize();
            $normalizedOutputFileName = $baseNameMd5 . $normalizedKey;
            if (!file_exists($normalizedOutputFileName)) {
                $imageResize = new ImageResize($imagePath);
                $imageResize->resize($this->getSampleSize(), $this->getSampleSize(), true);
                $imageResize->save($this->getTemporaryDirectory() . $normalizedOutputFileName);
                unset($imageResize);
            }
            $normalized[$this->getTemporaryDirectory() . $normalizedOutputFileName] = $imagePath;
        } else {
            throw new Exception('Image not found :' . $imagePath);
        }
        return $normalized;
    }

    /**
     * @return int
     */
    public function getSampleSize()
    {
        return $this->sampleSize;
    }

    /**
     * @param int $sampleSize
     */
    public function setSampleSize($sampleSize)
    {
        $this->sampleSize = $sampleSize;
    }

    public function getTemporaryDirectory()
    {
        return $this->temporaryDirectory;
    }

    private function setTemporaryDirectory($directory)
    {
        if (is_null($directory)) {
            $this->temporaryDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . '_fastImageCompare' . DIRECTORY_SEPARATOR;
        } else {
            $this->temporaryDirectory = $directory . DIRECTORY_SEPARATOR . '_fastImageCompare' . DIRECTORY_SEPARATOR;
        }
        if (!file_exists($this->getTemporaryDirectory())) {
            mkdir($this->getTemporaryDirectory(), $this->getTemporaryDirectoryPermissions(), true);
            // it seems that vagrant has problems with setting permissions when creating directory so lets chmod it directly
            chmod($this->getTemporaryDirectory(), $this->getTemporaryDirectoryPermissions());
        }
        if (!is_writable($this->getTemporaryDirectory())) throw new \Exception('Temporary directory ' . $this->getTemporaryDirectory() . ' is not writable');
    }


    /**
     * Internal compare images, this method assumes that images are in equal sizes
     *
     * @param $imageLeft
     * @param $imageRight
     * @return float
     */
    private function compareImages($imageLeft, $imageRight)
    {
        $imageInstanceLeft = new \imagick();
        $imageInstanceRight = new \imagick();
        $imageInstanceLeft->SetOption('fuzz', $this->getFuzzPercentage() . '%');

        $imageInstanceLeft->readImage($imageLeft);
        $imageInstanceRight->readImage($imageRight);

//        $imageInstanceLeft->setColorspace($this->compareColorSpace);
//        $imageInstanceRight->setColorspace($this->compareColorSpace);
        //  $imageInstanceLeft->normalizeImage(\Imagick::CHANNEL_GRAY);
        //  $imageInstanceRight->normalizeImage(\Imagick::CHANNEL_GRAY);

        // compare the images using METRIC=1 (Absolute Error)//Imagick::METRIC_MEANABSOLUTEERROR
        $compareResult = $imageInstanceLeft->compareImages($imageInstanceRight, \Imagick::METRIC_MEANABSOLUTEERROR)[1];
        $imageInstanceLeft->clear();
        $imageInstanceRight->clear();
        unset($imageInstanceLeft);
        unset($imageInstanceRight);
        return $compareResult;
    }

    /**
     * @return int
     */
    public function getFuzzPercentage()
    {
        return $this->fuzzPercentage;
    }

    /**
     * @param int $fuzzPercentage
     */
    public function setFuzzPercentage($fuzzPercentage)
    {
        $this->fuzzPercentage = $fuzzPercentage;
    }

    /**
     * @param array $images
     * @param float $enoughDifference
     * @param int $matchMode
     * @return array
     */
    public function extractUniques(array $images, $enoughDifference = 0.05, $matchMode = FastImageCompare::PREFER_ANY)
    {
        //find duplicates
        $duplicatesMap = $this->extractDuplicatesMap($images, $enoughDifference);
        $duplicates = array_keys($duplicatesMap);
        //remove all duplicates from input
        $withoutDuplicates = array_diff($images, $duplicates);
        //add one duplicate based on fight
        $picked = [];
        foreach ($duplicates as $duplicate) {
            $dupFromMap = $duplicatesMap[$duplicate];
            //if not already picked up , pick only one duplicate
            if (!in_array($duplicate, $picked)) {
                $keys = array_keys($dupFromMap);
                $diff = array_intersect($keys, $picked);
                if (count($diff) == 0) {
                    $picked[] = $this->matchSelect($duplicatesMap, $duplicate, $matchMode);
                }
            }
        }
        return array_merge($picked, $withoutDuplicates);
    }


    /**
     * Return duplicate map
     * eg. [img1] => [dup1,dup2], [dup1] => [img1,dup2] etc .
     * @param array $inputImages
     * @param float $enoughDifference
     * @return array
     */
    private function extractDuplicatesMap(array $inputImages, $enoughDifference = 0.05)
    {
        $compared = $this->compareArray($inputImages);
        $output = [];
        foreach ($compared as $data) {
            if ($data[2] <= $enoughDifference) {
                if (!isset($output[$data[0]])) $output[$data[0]] = array();
                if (!isset($output[$data[1]])) $output[$data[1]] = array();
                $output[$data[0]][$data[1]] = $data[2];
                $output[$data[1]][$data[0]] = $data[2];
            }
        }
        return $output;
    }

    /**
     * @param $map
     * @param $duplicate
     * @param int $matchMode
     * @return int|null|string
     */
    private function matchSelect($map, $duplicate, $matchMode = FastImageCompare::PREFER_ANY)
    {
        $mapEntry = $map[$duplicate];
        switch ($matchMode) {
            case self::PREFER_LARGER_DIFFERENCE:
                //add $duplicate to $mapEntry with maximum difference in $mapEntry
                $maxDiff = 0;
                foreach ($mapEntry as $entry => $differenceValue)
                    if ($entry === $duplicate)
                        $maxDiff = max($maxDiff, $differenceValue);
                $mapEntry[$duplicate] = $maxDiff;
                $sorted = ($mapEntry);
                arsort($sorted);
                reset($sorted);
                return key($sorted);
                break;
            case self::PREFER_LOWER_DIFFERENCE:
                $maxDiff = 0;
                foreach ($mapEntry as $entry => $differenceValue)
                    if ($entry === $duplicate)
                        $maxDiff = min($maxDiff, $differenceValue);

                $mapEntry[$duplicate] = $maxDiff;
                $sorted = ($mapEntry);
                asort($sorted);
                reset($sorted);
                return key($sorted);
                break;

            case self::PREFER_LARGER_IMAGE:
                $values = array_keys($mapEntry);
                array_push($values, $duplicate);
                $output = array();
                foreach ($values as $imagePath) {
                    $size = $this->getImageSizerInstance()->getImageSize($imagePath);
                    if ($size)
                        $output[$imagePath] = $size['width'] * $size['height'];
                    //sort by size
                }
                arsort($output);
                reset($output);
                return key($output);
                break;
            default:
                return $duplicate;// or key($mapEntry);
        }
    }

    private function getImageSizerInstance()
    {
        if (is_null($this->imageSizerInstance)) $this->imageSizerInstance = new FastImageSize();
        return $this->imageSizerInstance;
    }

    public function clear()
    {
        //TODO
    }
}