<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (RespectSanitizer.php)
 */


namespace Xibo\Support\Sanitizer;


use Illuminate\Support\Collection;
use Jenssegers\Date\Date;
use Respect\Validation\Validator as v;

class RespectSanitizer implements SanitizerInterface
{
    /** @var  Collection */
    private $collection;

    /**
     * Default Options
     * @var array
     */
    private $defaultOptions = [
        'default' => null,
        'rules' => [],
        'throw' => null,
        'throwClass' => null,
        'throwMessage' => null
    ];

    /**
     * @inheritdoc
     */
    public function setCollection($collection)
    {
        if (is_array($collection))
            $this->collection = new Collection($collection);
        else
            $this->collection = $collection;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setDefaultOptions($options)
    {
        $this->defaultOptions = array_merge($this->defaultOptions, $options);

        return $this;
    }

    /**
     * Return a failure
     * @param $options
     * @throws \Exception
     */
    private function failure($options)
    {
        $throw = $options['throw'];

        if (!is_null($throw))
            throw $throw;
        else if (is_callable($throw))
            return $throw();
        else if ($options['throwClass'] !== null)
            throw new $options['throwClass']($options['throwMessage']);
        else
            throw new \InvalidArgumentException('Invalid Argument');
    }

    /**
     * Return a failure or default
     * @param $options
     * @return mixed
     */
    private function failureNotExists($options)
    {
        if (is_null($options['throw']) && is_null($options['throwClass']))
            return $options['default'];

        return $this->failure($options);
    }

    /**
     * @inheritdoc
     */
    public function getInt($key, $options = [])
    {
        $options = array_merge($this->defaultOptions, $options);
        $options['throwMessage'] = str_replace('{{param}}', $key, $options['throwMessage']);

        if (!$this->collection->has($key))
            return $this->failureNotExists($options);

        $value = $this->collection->get($key);

        // Validate the parameter
        if (!v::intVal()->addRules($options['rules'])->validate($value)) {
            return $this->failure($options);
        } else {
            return intval($value);
        }
    }

    /**
     * @inheritdoc
     */
    public function getDouble($key, $options = [])
    {
        $options = array_merge($this->defaultOptions, $options);

        if (!$this->collection->has($key))
            return $this->failureNotExists($options);

        $value = $this->collection->get($key);

        // Validate the parameter
        if (!v::numeric()->addRules($options['rules'])->validate($value)) {
            return $this->failure($options);
        } else {
            return doubleval($value);
        }
    }

    /**
     * @inheritdoc
     */
    public function getString($key, $options = [])
    {
        $options = array_merge($this->defaultOptions, $options);

        if (!$this->collection->has($key))
            return $this->failureNotExists($options);

        $value = $this->collection->get($key);

        // Validate the parameter
        if (!v::stringType()->notEmpty()->addRules($options['rules'])->validate($value)) {
            return $this->failure($options);
        } else {
            return filter_var($value, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
        }
    }

    /**
     * @inheritdoc
     */
    public function getDate($key, $options = [])
    {
        $options = array_merge($this->defaultOptions, $options);

        if (!$this->collection->has($key))
            return $this->failureNotExists($options);

        $value = $this->collection->get($key);

        if ($value instanceof Date)
            return $value;

        // Validate the parameter
        $format = 'Y-m-d H:i:s';
        if (!v::date($format)->addRules($options['rules'])->validate($value)) {
            return $this->failure($options);
        } else {
            try {
                return Date::createFromFormat($format, $value);
            } catch (\Exception $e) {
                return $this->failure($options);
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function getArray($key, $options = [])
    {
        $options = array_merge($this->defaultOptions, $options);

        if (!$this->collection->has($key))
            return $this->failureNotExists($options);

        $value = $this->collection->get($key);

        // Validate the parameter
        if (!v::arrayType()->addRules($options['rules'])->validate($value)) {
            return $this->failure($options);
        } else {
            return $value;
        }
    }

    /**
     * @inheritdoc
     */
    public function getIntArray($key, $options = [])
    {
        $options = array_merge($this->defaultOptions, $options);

        if (!$this->collection->has($key))
            return $this->failureNotExists($options);

        $value = $this->collection->get($key);

        // Validate the parameter
        if (!v::arrayType()->addRules($options['rules'])->validate($value)) {
            return $this->failure($options);
        } else {
            return array_map('intval', $value);
        }
    }

    /**
     * @inheritdoc
     */
    public function getCheckbox($key)
    {
        if (!$this->collection->has($key))
            return false;

        $value = $this->collection->get($key);

        // Validate the parameter
        return ($value === 'on' || $value === 1 || $value === '1' || $value === 'true' || $value === true);
    }
}