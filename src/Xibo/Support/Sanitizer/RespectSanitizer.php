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
        'throw' => null
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
     * @param $throw
     * @return null
     * @throws \Exception
     */
    private function failure($throw)
    {
        if (is_null($throw))
            return $throw;
        else if (is_callable($throw))
            return $throw();
        else
            throw new \InvalidArgumentException('Invalid Argument');
    }

    /**
     * Return a failure or default
     * @param $throw
     * @param $default
     * @return mixed
     */
    private function failureNotExists($throw, $default)
    {
        if (is_null($throw))
            return $default;

        return $this->failure($throw);
    }

    /**
     * @inheritdoc
     */
    public function getInt($key, $options = [])
    {
        $options = array_merge($this->defaultOptions, $options);

        if (!$this->collection->has($key))
            return $this->failureNotExists($options['throw'], $options['default']);

        $value = $this->collection->get($key);

        // Validate the parameter
        if (!v::intVal()->addRules($options['rules'])->validate($value)) {
            return $this->failure($options['throw']);
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
            return $this->failureNotExists($options['throw'], $options['default']);

        $value = $this->collection->get($key);

        // Validate the parameter
        if (!v::numeric()->addRules($options['rules'])->validate($value)) {
            return $this->failure($options['throw']);
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
            return $this->failureNotExists($options['throw'], $options['default']);

        $value = $this->collection->get($key);

        // Validate the parameter
        if (!v::stringType()->notEmpty()->addRules($options['rules'])->validate($value)) {
            return $this->failure($options['throw']);
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
            return $this->failureNotExists($options['throw'], $options['default']);

        $value = $this->collection->get($key);

        if ($value instanceof Date)
            return $value;

        // Validate the parameter
        $format = 'Y-m-d H:i:s';
        if (!v::date($format)->addRules($options['rules'])->validate($value)) {
            return $this->failure($options['throw']);
        } else {
            try {
                return Date::createFromFormat($format, $value);
            } catch (\Exception $e) {
                return $this->failure($options['throw']);
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
            return $this->failureNotExists($options['throw'], $options['default']);

        $value = $this->collection->get($key);

        // Validate the parameter
        if (!v::arrayType()->addRules($options['rules'])->validate($value)) {
            return $this->failure($options['throw']);
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
            return $this->failureNotExists($options['throw'], $options['default']);

        $value = $this->collection->get($key);

        // Validate the parameter
        if (!v::arrayType()->addRules($options['rules'])->validate($value)) {
            return $this->failure($options['throw']);
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