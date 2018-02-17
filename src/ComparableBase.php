<?php

namespace pepeEpe\FastImageCompare;


abstract class ComparableBase implements IComparable
{

    private $comparableMode = IComparable::PASSTHROUGH;

    /**
     * @var INormalizable[]
     */
    private $registeredNormalizers = [];


    /**
     * @var string
     */
    private $shortClassName;

    public function __construct()
    {
        $this->shortClassName = Utils::getClassNameWithoutNamespace($this);
    }

    /**
     * @return string
     */
    private function getShortClassName()
    {
        return $this->shortClassName;
    }

    /**
     * @return int
     */
    public function getComparableMode()
    {
        return $this->comparableMode;
    }

    /**
     * @param int $comparableMode
     */
    public function setComparableMode($comparableMode)
    {
        $this->comparableMode = $comparableMode;
    }


    /**
     * Register new Normalizer
     * @param INormalizable $normalizerInstance
     */
    public function registerNormalizer(INormalizable $normalizerInstance)
    {
        $this->registeredNormalizers[] = $normalizerInstance;
    }

    /**
     * @param INormalizable[] $normalizerInstances
     */
    public function setNormalizers(array $normalizerInstances)
    {
        $this->registeredNormalizers = $normalizerInstances;
    }

    /**
     * @return INormalizable[]
     */
    public function getNormalizers()
    {
        return $this->registeredNormalizers;
    }

    /**
     * Clear normalizers
     */
    public function clearNormalizers()
    {
        $this->setNormalizers([]);
    }

    final public function difference($inputLeft,$inputRight,$enoughDifference,FastImageCompare $instance)
    {
        $normalizedLeft     = $this->normalize($inputLeft,$instance->getTemporaryDirectory());
        $normalizedRight    = $this->normalize($inputRight,$instance->getTemporaryDirectory());

        $cacheKey = $this->getShortClassName().'-'.$this->generateCacheKey($normalizedLeft,$normalizedRight);
        $cacheKey.='-'.md5($normalizedLeft).'-vs-'.md5($normalizedRight);

        if (!is_null($instance->getCacheAdapter())) {
            $item = $instance->getCacheAdapter()->getItem($cacheKey);
            if ($item->isHit()) {
                $r = $item->get();
                return $r;
            } else {
                $result = $this->calculateDifference($normalizedLeft, $normalizedRight, $inputLeft, $inputRight, $enoughDifference, $instance);
                $item->set($result);
                $saved = $instance->getCacheAdapter()->save($item);
                if (!$saved) throw new \Exception('Cant save cache');
                return $result;
            }
        } else {
            return $this->calculateDifference($normalizedLeft, $normalizedRight, $inputLeft, $inputRight, $enoughDifference, $instance);
        }
    }

    private function normalize($input,$tempDir)
    {
        foreach ($this->getNormalizers() as $normalizer){
            if (file_exists($input)) {
                $cacheFileName = $normalizer->getCachedFile($input,$tempDir);
                if (!file_exists($cacheFileName)) {
                    $input = $normalizer->normalize($input,$cacheFileName,$tempDir);
                } else {
                    $input = $cacheFileName;
                }
            }
        }
        return $input;
    }

}
