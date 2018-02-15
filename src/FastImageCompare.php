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

    private $debugEnabled = false;

    /**
     * @var
     */
    protected $temporaryDirectory;

    /**
     * @var int
     */
    protected $temporaryDirectoryPermissions = 0755;

    /**
     * @var null
     */
    static $imageSizerInstance = null;

    /**
     * @var IComparable[]
     */
    private $registeredComparators = [];

    /**
     * @return bool
     */
    public function isDebugEnabled()
    {
        return $this->debugEnabled;
    }

    /**
     * @param bool $debugEnabled
     */
    public function setDebugEnabled($debugEnabled)
    {
        $this->debugEnabled = $debugEnabled;
    }



    /**
     * FastImageCompare constructor.
     *
     *
     * @param null $absoluteTemporaryDirectory When null, library will use system temporary directory
     * @param IComparable[]|IComparable|null $comparators comparator instance(s), when null a default comparator will be registered @see ComparatorImageMagick with metric MEAN ABSOLUTE ERROR
     * @throws \Exception
     */
    public function __construct($absoluteTemporaryDirectory = null, $comparators = null)
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
        //$normalizedImages = $this->internalNormalizeArray($inputImages);
        //$normalizedImagesIndexed = array_keys($normalizedImages);
        $imageNameKeys = array_keys($inputImages);
        //compare each with each
        for ($x = 0; $x < count($inputImages) - 1; $x++) {
            for ($y = $x + 1; $y < count($inputImages); $y++) {
                $leftInput = $inputImages[$imageNameKeys[$x]];
                $rightInput = $inputImages[$imageNameKeys[$y]];
                $compareResult = $this->internalCompareImage($leftInput, $rightInput,$leftInput,$rightInput,$enoughDifference);
                $output[] = [$leftInput, $rightInput, $compareResult];
            }
        }
        return $output;
    }

//    /**
//     * Creates normalized [aspectRatio & pixelCount ] version of images from array $images
//     *
//     * Common resolution is used for all input sampleSize x sampleSize @see FastImageCompare::setSampleSize()
//     * @param array $images
//     * @return string[] normalized images absolute paths
//     * @throws \Exception
//     */
//    protected function internalNormalizeArray(array $images)
//    {
//        $images = array_unique($images);
//        $normalized = [];
//        foreach ($images as $imagePath) {
//            $normalized = array_merge($normalized,$this->internalNormalizeImage($imagePath));
//        }
//        return $normalized;
//    }
//
//    /**
//     * @param $imagePath
//     * @return array
//     * @throws \Exception
//     */
//    protected function internalNormalizeImage($imagePath)
//    {
//        $normalized = [];
//        $normalizers = $this->getNormalizers();
//
//        if (count($normalizers) == 0){
//            //without normalizers passthru
//            $normalized[$imagePath] = $imagePath;
//        } else {
//            foreach ($normalizers as $normalizer) {
//                $result = $normalizer->process($imagePath, $this->getTemporaryDirectory(), $normalized);
//                $normalized = array_merge($normalized, $result);
//            }
//        }
//        return $normalized;
//    }

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
        //$results = 2.0; //max difference
        $comparatorsSummary = 0.0;
        $comparatorsSummarizedInstances = 0;
        foreach ($this->registeredComparators as $comparatorIndex => $comparatorInstance)
        {
            $calculatedDifference = $comparatorInstance->difference($imageLeftNormalized,$imageRightNormalized,$enoughDifference,$this);
//            $this->printDebug("Compare using ".get_class($comparatorInstance)." ".basename($imageLeftOriginal).' vs '.basename($imageRightOriginal).' | S = '.$enoughDifference,['resultDifference'=>$calculatedDifference]);
            /**
             * jesli komparator dziala w trybie dokladnym @see IComparable::EXCLUDE, tzn ze jesli znajdzie 100% roznicy to nie trzeba dalej porownywac
             * i moze zostac zwrocony wynik , w przeciwnym wypadku niech kontynuuje [PASSTHROUGH] i sprawdza nastepne
             * komparatory
             */
            if ($comparatorInstance->getComparableMode() == IComparable::EXCLUDE && $calculatedDifference <= $enoughDifference)
            {
                return $calculatedDifference;
            }
            /**
             * Jesli przekazujemy dalej , musimy wziac pod uwage wszystkie komparatory i na podstawie wyniku ze wszystkich
             * zadecydowac czy jest rowny czy rozny
             */


            if ($comparatorInstance->getComparableMode() != IComparable::EXCLUDE)
            {
                $comparatorsSummary += $calculatedDifference;
                $comparatorsSummarizedInstances++;
            }
        }

        /**
         * obliczmy srednia z komparatorow
         */
        return ($comparatorsSummary > 0 && $comparatorsSummarizedInstances > 0) ? floatval($comparatorsSummary) / floatval($comparatorsSummarizedInstances) : $comparatorsSummary;
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
        $this->printDebug('extractDuplicatesMap',$output);
        $this->printDebug('$compared',$compared);
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
        $oldCache = Utils::getFilesOlderBy($this->getTemporaryDirectory(),$lifeTimeSeconds);
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
            @chmod($this->getTemporaryDirectory(), $this->getTemporaryDirectoryPermissions());
        }
        if (!is_writable($this->getTemporaryDirectory())) throw new \Exception('Temporary directory ' . $this->getTemporaryDirectory() . ' is not writable');
    }


    /**
     * @return FastImageSize|null
     */
    private function getImageSizerInstance()
    {
        if (is_null(self::$imageSizerInstance)) self::$imageSizerInstance = new FastImageSize();
        return self::$imageSizerInstance;
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
     * Register new comparator with default mode @see IComparable::PASSTHROUGH constants
     * @param IComparable $comparatorInstance
     * @param int $mode IComparable mode
     */
    public function registerComparator(IComparable $comparatorInstance, $mode = IComparable::PASSTHROUGH)
    {
        $this->registeredComparators[] = $comparatorInstance;
        $comparatorInstance->setComparableMode($mode);
    }

    /**
     * @param IComparable[] $comparators
     */
    public function setComparators(array $comparators)
    {
        $this->registeredComparators = $comparators;
    }

    /**
     * @return IComparable[]
     */
    public function getComparators()
    {
        return $this->registeredComparators;
    }

    /**
     * Clear comparators
     */
    public function clearComparators()
    {
        $this->setComparators([]);
    }

    /**
     * UTILS
     */

    /**
     * @param $imagePath
     * @return array|bool
     */
    public function getImageSize($imagePath)
    {
        return $this->getImageSizerInstance()->getImageSize($imagePath);
    }

    private function printDebug($label,$data)
    {
        if (!$this->isDebugEnabled()) return;
        echo "<br/><b>$label</b>";
        if (!is_null($data))
        dump($data);
        echo '<br/>';
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