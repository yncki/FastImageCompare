<?php

namespace pepeEpe\FastImageCompare;

abstract class ClassificableBase implements IClassificable
{


    /**
     * @var string
     */
    private $shortClassName;

    public function __construct()
    {
        $this->shortClassName = Utils::getClassNameWithoutNamespace($this);
    }

    /**
     * @param $inputFile
     * @param $instance FastImageCompare
     * @return string[]
     */
    final public function classify($inputFile, FastImageCompare $instance)
    {
        if (!is_null($instance->getCacheAdapter())) {

            $cacheKey = $this->getShortClassName() . '-' . $this->generateCacheKey($inputFile);
            $cacheKey .= '-' . md5($inputFile);

            $item = $instance->getCacheAdapter()->getItem($cacheKey);
            if ($item->isHit()) {
                $result = $item->get();
                return $result;
            } else {
                $result = $this->internalClassify($inputFile, $instance);
                $item->set($result);
                $instance->getCacheAdapter()->save($item);
                return $result;
            }

        }
        return $this->internalClassify($inputFile, $instance);
    }

    /**
     * @return string
     */
    private function getShortClassName()
    {
        return $this->shortClassName;
    }

    /**
     * @param $inputFile
     * @param $instance FastImageCompare
     * @return string[]
     */
    abstract protected function internalClassify($inputFile, FastImageCompare $instance);

}