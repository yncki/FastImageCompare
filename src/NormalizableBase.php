<?php
/**
 * (c) Paweł Plewa <pawel.plewa@gmail.com> 2018
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 *
 */

namespace pepeEpe\FastImageCompare;


abstract class NormalizableBase implements INormalizable {

    protected $ensuredCacheDirExists = null;

    /**
     * @var
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
     * @param $filePath
     * @return string
     */
    private function buildCachePath($filePath){
        return $this->getShortClassName().DIRECTORY_SEPARATOR.$this->getCacheKey($filePath);
    }

    public function getCachedFile($filePath,$temporaryDirectory)
    {
        $dest =  $temporaryDirectory.$this->buildCachePath($filePath);
        if (!$this->ensuredCacheDirExists){
            $this->ensuredCacheDirExists  = dirname($dest);
            if (!file_exists($this->ensuredCacheDirExists)){
                @mkdir($this->ensuredCacheDirExists,fileperms($temporaryDirectory));
            }
        }
        return $dest.'-'.basename($filePath);
    }

}