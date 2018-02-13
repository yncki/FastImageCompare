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

    protected $temporaryDirectory;
    protected $temporaryDirectoryPermissions = 0755;

    private $imageSizerInstance = null;

    /**
     * @var IComparable[]
     */
    private $registeredComparators = [];

    /**
     * @var INormalizable[]
     */
    private $registeredNormalizers = [];

    /**
     * FastImageCompare constructor.
     *
     *
     * @param null $absoluteTemporaryDirectory When null, library will use system temporary directory
     * @param IComparable[]|IComparable|null $comparators comparator instance(s), when null a default comparator will be registered @see ComparatorImageMagick with metric MEAN ABSOLUTE ERROR
     * @param INormalizable[]|INormalizable|null $normalizers normalizer instance(s), when null a default normalizer will be registered @see NormalizerSizeType with sampleSize = 8
     * @throws \Exception
     */
    public function __construct($absoluteTemporaryDirectory = null, $comparators = null, $normalizers = null)
    {
        $this->setTemporaryDirectory($absoluteTemporaryDirectory);

        if (is_null($comparators)) {
            //register default comparator
            $this->registerComparator(new ComparatorImageMagick(ComparatorImageMagick::METRIC_MAE));
        } elseif (is_array($comparators)) {
            //set array of comparators
            $this->setComparators($comparators);
        } elseif ($comparators instanceof IComparable){
            //register
            $this->registerComparator($comparators);
        }

        if (is_null($normalizers)){
            $this->registerNormalizer(new NormalizerSizeType(8));
        } elseif (is_array($normalizers)){
            $this->setNormalizers($normalizers);
        } elseif ($normalizers instanceof INormalizable){
            $this->registerNormalizer($normalizers);
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
     * @param $enoughDifference float
     * @return array
     * @throws \Exception
     */
    public function compareArray(array $inputImages,$enoughDifference)
    {
        $output = [];
        $normalizedImages = $this->internalNormalizeArray($inputImages);
        $normalizedImagesIndexed = array_keys($normalizedImages);
        $imageNameKeys = array_keys($normalizedImagesIndexed);
        //compare each with each
        for ($x = 0; $x < count($normalizedImagesIndexed) - 1; $x++) {
            for ($y = $x + 1; $y < count($normalizedImagesIndexed); $y++) {
                $imageLeftNormalized = $normalizedImagesIndexed[$imageNameKeys[$x]];
                $imageRightNormalized = $normalizedImagesIndexed[$imageNameKeys[$y]];
                $compareResult = $this->internalCompareImage($imageLeftNormalized, $imageRightNormalized,$normalizedImages[$imageLeftNormalized],$normalizedImages[$imageRightNormalized],$enoughDifference);
                $output[] = [$normalizedImages[$imageLeftNormalized], $normalizedImages[$imageRightNormalized], $compareResult];
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
    protected function internalNormalizeArray(array $images)
    {
        $images = array_unique($images);
        $normalized = [];
        foreach ($images as $imagePath) {
            $normalized = array_merge($normalized,$this->internalNormalizeImage($imagePath));
        }
        return $normalized;
    }

    /**
     * @param $imagePath
     * @return array
     * @throws \Exception
     */
    protected function internalNormalizeImage($imagePath){
        $normalized = [];
        foreach ($this->getNormalizers() as $normalizer){
            $result = $normalizer->process($imagePath,$this->getTemporaryDirectory(),$normalized);
            $normalized = array_merge($normalized,$result);
        }
        return $normalized;
    }

    /**
     * Internal method to compare images by registered comparators
     *
     * @param $imageLeftNormalized string
     * @param $imageRightNormalized string
     * @param $imageLeftOriginal
     * @param $imageRightOriginal
     * @param float $enoughDifference
     * @return float
     * @throws \Exception
     */
    private function internalCompareImage($imageLeftNormalized, $imageRightNormalized, $imageLeftOriginal, $imageRightOriginal, $enoughDifference)
    {
        $results = 2.0; //max difference
        $subResult = [];
        foreach ($this->registeredComparators as $comparatorIndex => $comparatorInstance){
            $diff = $comparatorInstance->calculateDifference($imageLeftNormalized,$imageRightNormalized,$imageLeftOriginal,$imageRightOriginal,$enoughDifference);
            //if we are looking for 0 difference ( totaly equal images ) and comparator returns 0 then return 0%
            if ($enoughDifference == 0 && $diff == 0) return 0.0;

            //else add to subResult
            $cls = get_class($comparatorInstance);
            $subResult[$cls] = $diff;

            //TODO pick from $subResult

            return $diff;

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
                    $picked[] = $this->preferredPick($duplicatesMap, $duplicate, $preferOnDuplicate);
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
    private function preferredPick($duplicateMap, $duplicateItem, $preferOnDuplicate = FastImageCompare::PREFER_LARGER_IMAGE)
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
                    $size = $this->getImageSize($imagePath);
                    if ($size) {
                        $output[$imagePath] = $size['width'] * $size['height'];
                    } else {
                        $output[$imagePath] = 0;
                    }
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
     * @param int $lifeTimeSeconds , set null to remove all files
     */
    public function clearCache($lifeTimeSeconds = null)
    {
        $oldCache = Utils::getFilesOlderBy($lifeTimeSeconds);
        Utils::removeFiles($oldCache);
    }

    /**
     * SETTERS & GETTERS
     */


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
     * @param IComparable $comparatorInstance
     */
    public function registerComparator(IComparable $comparatorInstance){
        $this->registeredComparators[] = $comparatorInstance;
    }

    /**
     * @param IComparable[] $comparators
     */
    public function setComparators(array $comparators){
        $this->registeredComparators = $comparators;
    }

    /**
     * @return IComparable[]
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
     * Register new Normalizer
     * @param INormalizable $normalizerInstance
     */
    public function registerNormalizer(INormalizable $normalizerInstance){
        $this->registeredNormalizers[] = $normalizerInstance;
    }

    /**
     * @param INormalizable[] $normalizerInstances
     */
    public function setNormalizers(array $normalizerInstances){
        $this->registeredNormalizers = $normalizerInstances;
    }

    /**
     * @return INormalizable[]
     */
    public function getNormalizers(){
        return $this->registeredNormalizers;
    }

    /**
     * Clear normalizers
     */
    public function clearNormalizers(){
        $this->setNormalizers([]);
    }

    /**
     * UTILS
     */

    /**
     * @param $imagePath
     * @return array|bool
     */
    public function getImageSize($imagePath){
        return $this->getImageSizerInstance()->getImageSize($imagePath);
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