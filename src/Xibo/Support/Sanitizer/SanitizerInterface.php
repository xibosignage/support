<?php
/*
 * Xibo Signage Ltd - http://www.xibosignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (SanitizerInterface.php)
 */


namespace Xibo\Support\Sanitizer;


use Illuminate\Support\Collection;
use Jenssegers\Date\Date;

interface SanitizerInterface
{
    /**
     * @param Collection|array $collection
     * @return $this
     */
    public function setCollection($collection);

    /**
     * Set default options
     * @param $options
     * @return $this
     */
    public function setDefaultOptions($options);

    /**
     * @param string $key The name of the key
     * @param array $options Options to apply to the operation
     * @return int
     * @throws \Exception
     */
    public function getInt($key, $options = []);

    /**
     * @param string $key The name of the key
     * @param array $options Options to apply to the operation
     * @return double
     * @throws \Exception
     */
    public function getDouble($key, $options = []);

    /**
     * @param string $key The name of the key
     * @param array $options Options to apply to the operation
     * @return string
     * @throws \Exception
     */
    public function getString($key, $options = []);

    /**
     * @param string $key The name of the key
     * @param array $options Options to apply to the operation
     * @return Date
     * @throws \Exception
     */
    public function getDate($key, $options = []);

    /**
     * @param string $key The name of the key
     * @param array $options Options to apply to the operation
     * @return array
     * @throws \Exception
     */
    public function getArray($key, $options = []);

    /**
     * @param string $key The name of the key
     * @param array $options Options to apply to the operation
     * @return int[]
     * @throws \Exception
     */
    public function getIntArray($key, $options = []);

    /**
     * @param string $key The name of the key
     * @return bool
     * @throws \Exception
     */
    public function getCheckbox($key);
}