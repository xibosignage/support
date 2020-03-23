<?php
/**
 * Copyright (c) 2020 Xibo Signage Ltd
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
     */
    public function getInt($key, $options = []);

    /**
     * @param string $key The name of the key
     * @param array $options Options to apply to the operation
     * @return double
     */
    public function getDouble($key, $options = []);

    /**
     * @param string $key The name of the key
     * @param array $options Options to apply to the operation
     * @return string
     */
    public function getString($key, $options = []);

    /**
     * @param string $key The name of the key
     * @param array $options Options to apply to the operation
     * @return Date
     */
    public function getDate($key, $options = []);

    /**
     * @param string $key The name of the key
     * @param array $options Options to apply to the operation
     * @return array
     */
    public function getArray($key, $options = []);

    /**
     * @param string $key The name of the key
     * @param array $options Options to apply to the operation
     * @return int[]
     */
    public function getIntArray($key, $options = []);

    /**
     * @param string $key The name of the key
     * @param array $options Options to apply to the operation
     * @return integer|bool
     */
    public function getCheckbox($key, $options = []);

    /**
     * @param string $key The name of the key
     * @return bool
     */
    public function hasParam($key);
}