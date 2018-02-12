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
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use SplFileInfo;

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

    /**
     * @var IImageComparator[]
     */
    private $registeredComparators = [];

    /**
     * FastImageCompare constructor.
     *
     *
     * @param null $absoluteTemporaryDirectory When null, library will use system temporary directory
     * @param int $sampleSize
     * @param IImageComparator[]|IImageComparator|null $comparators array of comparator instances, when null a default comparator will be registered @see ImageMagickComparator with metric MEAN ABSOLUTE ERROR and fuzz=2
     * @throws \Exception
     */
    public function __construct($absoluteTemporaryDirectory = null, $sampleSize = 8, $comparators = null)
    {
        $this->setTemporaryDirectory($absoluteTemporaryDirectory);
        $this->setSampleSize($sampleSize);
        if (is_null($comparators)) {
            $this->registerComparator(new ImageMagickComparator());
        } elseif (is_array($comparators)) {
            $this->setComparators($comparators);
        } elseif ($comparators instanceof IImageComparator){
            $this->registerComparator($comparators);
        }
    }

    /**
     * @param array $inputImages
     * @param float $enough percentage 0..1
     * @return array
     * @throws \Exception
     */
    public function extractDuplicates(array $inputImages, $enough = 0.05)
    {
        $output = [];
        $compared = $this->compareArray($inputImages,$enough);
        foreach ($compared as $data) {
            if ($data[2] <= $enough) {
                $output[] = $data[0];
                $output[] = $data[1];
            }
        }
        return array_unique($output);
    }

    /**
     * Compares each with each using registered comparators and return difference percentage in range 0..1
     * @param array $inputImages
     * @return array
     * @throws \Exception
     */
    public function compareArray(array $inputImages,$enoughDifference)
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
                $compareResult = $this->internalCompare($imageLeft, $imageRight,$enoughDifference);
                $output[] = [$normalizedImages[$imageLeft], $normalizedImages[$imageRight], $compareResult];
            }
        }
        return $output;
    }

    /**
     * Creates normalized [aspectRatio & pixelCount ] version of images from array $images
     *
     * Common resolution is used for all input sampleSize x sampleSize @see FastImageCompare::setSampleSize()
     * @param array $images
     * @return string[] normalized images absolute paths
     * @throws \Exception
     */
    protected function normalizeArray(array $images)
    {
        $images = array_unique($images);
        $normalized = [];
        foreach ($images as $imagePath) {
            $normalized = array_merge($normalized,$this->normalize($imagePath));
        }
        return $normalized;
    }


    /**
     * @param $imagePath
     * @return array
     * @throws \Gumlet\ImageResizeException|\Exception
     */
    protected function normalize($imagePath){
        $normalized = [];
        if (file_exists($imagePath)) {
            $baseName = basename($imagePath);
            $baseNameMd5 = md5($baseName);
            $normalizedKey = '.n.' . $this->getSampleSize();
            $normalizedOutputFileName = $baseNameMd5 . $normalizedKey;
            if (!file_exists($this->getTemporaryDirectory().$normalizedOutputFileName)) {
                $imageResize = new ImageResize($imagePath);
                $imageResize->quality_jpg = 100;
                $imageResize->quality_png = 9;
                $imageResize->quality_webp = 100;
                $imageResize->quality_truecolor = true;
                $imageResize->resize($this->getSampleSize(), $this->getSampleSize(), true);
                $imageResize->save($this->getTemporaryDirectory() . $normalizedOutputFileName);
                unset($imageResize);
            }
            $normalized[$this->getTemporaryDirectory() . $normalizedOutputFileName] = $imagePath;
        } else {
            throw new \Exception('Image not found :' . $imagePath);
        }
        return $normalized;
    }

    /**
     * Internal method to compare images, this method assumes that images are in equal sizes
     *
     * @param $imageLeft string
     * @param $imageRight string
     * @param float $enoughDifference
     * @return float[]
     * @throws \Exception
     */
    private function internalCompare($imageLeft, $imageRight, $enoughDifference)
    {
        $results = [];
        foreach ($this->registeredComparators as $comparatorIndex => $comparatorInstance){
            $results = $comparatorInstance->calculateDifference($imageLeft,$imageRight,$enoughDifference);    //TODO
        }
        return $results;
    }

    /**
     * @param array $images
     * @param float $enoughDifference
     * @param int $preferOnDuplicate
     * @return array
     * @throws \Exception
     */
    public function extractUniques(array $images, $enoughDifference = 0.05, $preferOnDuplicate = FastImageCompare::PREFER_LARGER_IMAGE)
    {
        //TODO $matchMode bit flags
//        if ($matchMode & PREFER_LARGER_IMAGE) {
//            echo "PREFER_LARGER_IMAGE is set\n";
//        }

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
                    $picked[] = $this->preferedPick($duplicatesMap, $duplicate, $preferOnDuplicate);
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
     * @throws \Exception
     */
    public function extractDuplicatesMap(array $inputImages, $enoughDifference = 0.05)
    {
        $compared = $this->compareArray($inputImages,$enoughDifference);
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
     * @param $duplicateMap
     * @param $duplicateItem
     * @param int $preferOnDuplicate
     * @return int|null|string
     */
    private function preferedPick($duplicateMap, $duplicateItem, $preferOnDuplicate = FastImageCompare::PREFER_LARGER_IMAGE)
    {
        $mapEntry = $duplicateMap[$duplicateItem];
        switch ($preferOnDuplicate) {
            case self::PREFER_LARGER_DIFFERENCE:
                //add $duplicate to $mapEntry with maximum difference in $mapEntry
                $maxDiff = 0;
                foreach ($mapEntry as $entry => $differenceValue)
                    if ($entry === $duplicateItem)
                        $maxDiff = max($maxDiff, $differenceValue);
                $mapEntry[$duplicateItem] = $maxDiff;
                $sorted = ($mapEntry);
                arsort($sorted);
                reset($sorted);
                return key($sorted);
                break;
            case self::PREFER_LOWER_DIFFERENCE:
                $maxDiff = PHP_INT_MAX;
                foreach ($mapEntry as $entry => $differenceValue)
                    if ($entry === $duplicateItem)
                        $maxDiff = min($maxDiff, $differenceValue);

                $mapEntry[$duplicateItem] = $maxDiff;
                $sorted = ($mapEntry);
                asort($sorted);
                reset($sorted);
                return key($sorted);
                break;

            case self::PREFER_LARGER_IMAGE:
                $values = array_keys($mapEntry);
                array_push($values, $duplicateItem);
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
                return $duplicateItem;// or key($mapEntry);
        }
    }


    /**
     * Clears files in cache folder older than $lifeTimeSeconds,
     * @param int $lifeTimeSeconds , set < 0 to remove all files
     */
    public function clearCache($lifeTimeSeconds = -1)
    {
        //TODO
    }

    /**
     * SETTERS & GETTERS
     */


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

    /**
     * @param $directory
     * @throws \Exception
     */
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
     * @return FastImageSize|null
     */
    private function getImageSizerInstance()
    {
        if (is_null($this->imageSizerInstance)) $this->imageSizerInstance = new FastImageSize();
        return $this->imageSizerInstance;
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
     * Register new comparator
     * @param IImageComparator $comparatorInstance
     */
    public function registerComparator(IImageComparator $comparatorInstance){
        $this->registeredComparators[] = $comparatorInstance;
    }

    /**
     * @param IImageComparator[] $comparators
     */
    public function setComparators(array $comparators){
        $this->registeredComparators = $comparators;
    }

    /**
     * @return IImageComparator[]
     */
    public function getComparators(){
        return $this->registeredComparators;
    }

    /**
     * Clear comparators
     */
    public function clearComparators(){
        $this->setComparators([]);
    }


    /**
     * UTILS
     */


    /**
     * @param $path
     * @param null $notLastModifiedDaysAgo
     * @return array
     */
    public static function getDirContentsFiles($path, $notLastModifiedDaysAgo = null) {
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
        $files = array();
        foreach ($rii as $file)
            /**
             * @var $file SplFileInfo
             */
            if (!$file->isDir()) {
                //jezeli podana jest data modyfikacji w dniach, tylko pliki ktore sa starsze niz ta data moga byc zwrocone
                if (!is_null($notLastModifiedDaysAgo)){
                    \Carbon\Carbon::setLocale('pl');
                    $dt = \Carbon\Carbon::createFromTimestamp($file->getMTime());
                    if ($notLastModifiedDaysAgo <= $dt->diffInDays()){
                        $files[] = $file->getPathname();
                    }
                } else {
                    $files[] = $file->getPathname();
                }
            }

        return array_unique($files);
    }

    /**
     * @see https://stackoverflow.com/a/8260942
     * @param $url
     * @return string
     */
    public static function normalizeUrl($url){
        $parts = parse_url($url);
        $path_parts = array_map('rawurldecode', explode('/', $parts['path']));
        return
            $parts['scheme'] . '://' .
            $parts['host'] .
            implode('/', array_map('rawurlencode', $path_parts))
            ;
    }

    public static function debug(array $input)
    {
        $root = $_SERVER['DOCUMENT_ROOT'];
        echo '<hr>';
        foreach ($input as $img) {
            $url = str_replace($root, '', $img);
           // $b = basename($img);
           // $url = '/temporary/_importer_api/'.$b;
            echo '<img style="height:40px;padding:4px;" src="' . $url . '"/>';//<br/>';
        }
    }

}