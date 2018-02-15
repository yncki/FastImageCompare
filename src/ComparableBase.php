<?php

namespace pepeEpe\FastImageCompare;


abstract class ComparableBase implements IComparable
{

    private $comparableMode = IComparable::PASSTHROUGH;

    /**
     * @var INormalizer[]
     */
    private $registeredNormalizers = [];

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
     * @param INormalizer $normalizerInstance
     */
    public function registerNormalizer(INormalizer $normalizerInstance)
    {
        $this->registeredNormalizers[] = $normalizerInstance;
    }

    /**
     * @param INormalizer[] $normalizerInstances
     */
    public function setNormalizers(array $normalizerInstances)
    {
        $this->registeredNormalizers = $normalizerInstances;
    }

    /**
     * @return INormalizer[]
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
        return $this->calculateDifference($normalizedLeft,$normalizedRight,$inputLeft,$inputRight,$enoughDifference);
    }

    protected function normalize($input,$tempDir){

        $output = $input;
        foreach ($this->getNormalizers() as $normalizer){
            $output = $normalizer->normalize($output,$tempDir);
        }
        return $output;
    }

}
