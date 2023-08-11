<?php
/**
 * Copyright (c) 2020 Xibo Signage Ltd
 */


namespace Xibo\Support\Sanitizer;


use Illuminate\Support\Collection;
use Carbon\Carbon;

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
     * Get the raw param from the collection
     * @param string $key The name of the key
     * @param array $options Options to apply to the operation
     * @return mixed
     */
    public function getParam($key, $options = []);

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
     * @return Carbon
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
     * @param array $options Options to apply to the operation
     * @return string
     */
    public function getHtml($key, $options = []);

    /**
     * @param string $key The name of the key
     * @return bool
     */
    public function hasParam($key);
}