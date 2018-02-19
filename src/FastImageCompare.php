<?php
/**
 * (c) Paweł Plewa <pawel.plewa@gmail.com> 2018
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 *
 */


namespace pepeEpe\FastImageCompare;

use FastImageSize\FastImageSize;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Class FastImageCompare
 *
 * By default it calculates difference of images using Mean Absolute Error metric ( MAE )
 *
 *
 * @package pepeEpe
 */
class FastImageCompare
{

    const PREFER_ANY = 1;
    const PREFER_LARGER_IMAGE = 2;
    const PREFER_SMALLER_IMAGE = 4;
    const PREFER_LOWER_DIFFERENCE = 8;
    const PREFER_LARGER_DIFFERENCE = 16;

    /**
     * @var CacheItemPoolInterface
     */
    private $cacheAdapter;

    /**
     * @var bool
     */
    private $debugEnabled = false;

    /**
     * @var
     */
    private $temporaryDirectory;

    /**
     * @var int
     */
    private $temporaryDirectoryPermissions = 0777;

    /**
     * @var null
     */
    static $imageSizerInstance = null;

    /**
     * @var IComparable[]
     */
    private $registeredComparators = [];

    /**
     * @var int
     */
    private $chunkSize = 8;

    /**
     * FastImageCompare constructor.
     *
     *
     * @param null $absoluteTemporaryDirectory When null, library will use system temporary directory
     * @param IComparable[]|IComparable|null $comparators comparator instance(s), when null - no comparators will be registered, when empty array a default comparator will be registered @see ComparatorImageMagick with metric MEAN ABSOLUTE ERROR
     * @param $cacheAdapter AdapterInterface
     */
    public function __construct($absoluteTemporaryDirectory = null, $comparators = [], $cacheAdapter = null)
    {
        $this->setTemporaryDirectory($absoluteTemporaryDirectory);
        $this->setCacheAdapter($cacheAdapter);

        if (is_null($comparators)) {

        } elseif (is_array($comparators)) {
            //set array of comparators
            if (count($comparators) == 0) {
                $this->registerComparator(new ComparatorImageMagick(ComparatorImageMagick::METRIC_MAE));
            } else {
                $this->setComparators($comparators);
            }
        } elseif ($comparators instanceof IComparable){
            //register
            $this->registerComparator($comparators);
        }

    }

    /**
     * Compares each with each using registered comparators and return difference percentage in range 0..1
     * @param array $inputImages
     * @param $enoughDifference float
     * @return array
     */
    private function compareArray(array $inputImages,$enoughDifference)
    {
        $inputImages = array_unique($inputImages);
        $output = [];
        $imageNameKeys = array_keys($inputImages);
        //compare each with each
        for ($x = 0; $x < count($inputImages) - 1; $x++) {
            for ($y = $x + 1; $y < count($inputImages); $y++) {
                $leftInput = $inputImages[$imageNameKeys[$x]];
                $rightInput = $inputImages[$imageNameKeys[$y]];
                $compareResult = $this->internalCompareImage($leftInput, $rightInput,$enoughDifference);
                $output[] = [$leftInput, $rightInput, $compareResult];
            }
        }
        return $output;
    }


    /**
     * Internal method to compare images by registered comparators
     *
     * @param $inputLeft string
     * @param $inputRight string
     * @param float $enoughDifference
     * @return float
     */
    private function internalCompareImage($inputLeft, $inputRight, $enoughDifference)
    {
        $comparatorsSummary = 0.0;
        $comparatorsSummarizedInstances = 0;
        foreach ($this->registeredComparators as $comparatorIndex => $comparatorInstance)
        {

            $calculatedDifference = $comparatorInstance->difference($inputLeft,$inputRight,$enoughDifference,$this);

            /**
             * jesli komparator dziala w trybie dokladnym @see IComparable::STRICT, tzn ze jesli znajdzie roznice to nie trzeba dalej porownywac
             * i moze zostac zwrocony wynik , w przeciwnym wypadku niech kontynuuje [PASSTHROUGH] i sprawdza nastepne
             * komparatory
             */
            if ($comparatorInstance->getComparableMode() == IComparable::STRICT && $calculatedDifference <= $enoughDifference)
            {
                return $calculatedDifference;
            }
            /**
             * Jesli przekazujemy dalej , musimy wziac pod uwage wszystkie komparatory i na podstawie wyniku ze wszystkich
             * zadecydowac czy jest rowny czy rozny
             */

            if ($comparatorInstance->getComparableMode() != IComparable::STRICT)
            {
                $comparatorsSummary += $calculatedDifference;
                $comparatorsSummarizedInstances++;
            }
        }

        /**
         * return avg from non STRICT comparators
         */
        return ($comparatorsSummary > 0 && $comparatorsSummarizedInstances > 0) ? floatval($comparatorsSummary) / floatval($comparatorsSummarizedInstances) : $comparatorsSummary;
    }


    /**
     * @param $imageA
     * @param $imageB
     * @param float $enoughDifference
     * @return bool
     */
    public function areSimilar($imageA,$imageB,$enoughDifference = 0.05){
        return (count($this->findDuplicates([$imageA,$imageB],$enoughDifference)) == 2);
    }

    /**
     * @param $imageA
     * @param $imageB
     * @param float $enoughDifference
     * @return bool
     */
    public function areDifferent($imageA,$imageB,$enoughDifference = 0.05){
        return !$this->areSimilar($imageA,$imageB,$enoughDifference);
    }

    /**
     * @param array $inputImages
     * @param float $enough percentage 0..1
     * @return array
     */
    public function findDuplicates(array $inputImages, $enough = 0.05)
    {
        $inputImages = array_unique($inputImages);
        $output = [];
        $compared = $this->compareArray($inputImages,$enough);
        foreach ($compared as $data) {
            if ($data[2] <= $enough) {
                $output[] = $data[0];
                $output[] = $data[1];
            }
        }
        sort($output);
        return array_unique($output);
    }

    /**
     * @param array $images
     * @param float $enoughDifference
     * @param int $preferOnDuplicate
     * @return array
     */
    public function findUniques(array $images, $enoughDifference = 0.05, $preferOnDuplicate = FastImageCompare::PREFER_LARGER_IMAGE)
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
        $s =  array_merge($picked, $withoutDuplicates);
        sort($s);
        return $s;
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
        //TODO implement better chunking , recursive
        $inputImages = array_unique($inputImages);
        $output = [];
        $chunks = array_chunk($inputImages,$this->getChunkSize(),true);
        $chunkedArray = [];
        $needRechunk = count($chunks) > 1;

        foreach ($chunks as $chunk) {
            $compared = $this->compareArray($chunk, $enoughDifference);
            foreach ($compared as $data)
            {
                if (!$needRechunk) {
                    if ($data[2] <= $enoughDifference) {
                        if (!isset($output[$data[0]])) $output[$data[0]] = array();
                        if (!isset($output[$data[1]])) $output[$data[1]] = array();
                        $output[$data[0]][$data[1]] = $data[2];
                        $output[$data[1]][$data[0]] = $data[2];
                    }
                } else {
                    $chunkedArray[] = $data[0];
                    $chunkedArray[] = $data[1];
                }
            }
        }

        if ($needRechunk){
            $output = [];
            $chunkedArray = array_unique($chunkedArray);
            $compared = $this->compareArray($chunkedArray, $enoughDifference);
            foreach ($compared as $data) {
                if ($data[2] <= $enoughDifference) {
                    if (!isset($output[$data[0]])) $output[$data[0]] = array();
                    if (!isset($output[$data[1]])) $output[$data[1]] = array();
                    $output[$data[0]][$data[1]] = $data[2];
                    $output[$data[1]][$data[0]] = $data[2];
                }
            }
        }

        $this->printDebug('extractDuplicatesMap',$output);
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
            case self::PREFER_SMALLER_IMAGE:
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
                if ($preferOnDuplicate == self::PREFER_LARGER_IMAGE) {
                    arsort($output);
                } else {
                    asort($output);
                }
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
        if (!is_null($this->getCacheAdapter())) $this->getCacheAdapter()->clear();
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
    public function setTemporaryDirectory($directory)
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
        if (!is_writable($this->getTemporaryDirectory())) {
            throw new \Exception('Temporary directory ' . $this->getTemporaryDirectory() . ' is not writable');
        }
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
     * @return int
     */
    public function getChunkSize()
    {
        return $this->chunkSize;
    }

    /**
     * @param int $chunkSize
     */
    public function setChunkSize($chunkSize)
    {
        $this->chunkSize = $chunkSize;
    }




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
     * @return CacheItemPoolInterface
     */
    public function getCacheAdapter()
    {
        return $this->cacheAdapter;
    }

    /**
     * @param CacheItemPoolInterface $cacheAdapter
     */
    public function setCacheAdapter(CacheItemPoolInterface $cacheAdapter = null)
    {
        $this->cacheAdapter = $cacheAdapter;
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
            $url = '/'.str_replace($root, '', $img);
           // $b = basename($img);
           // $url = '/temporary/_importer_api/'.$b;
            echo '<img style="height:40px;padding:4px;" src="' . $url . '"/>';//<br/>';
        }
    }

}