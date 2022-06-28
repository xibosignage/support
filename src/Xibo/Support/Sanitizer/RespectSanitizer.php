<?php
/**
 * Copyright (c) 2020 Xibo Signage Ltd
 */
namespace Xibo\Support\Sanitizer;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Respect\Validation\Validator as v;
use Xibo\Support\Exception\InvalidArgumentException;

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
        'defaultOnNotExists' => true,
        'rules' => [],
        'throw' => null,
        'throwClass' => null,
        'throwMessage' => null,
        'key' => null,
        'dateFormat' => 'Y-m-d H:i:s',
        'checkboxReturnInteger' => false,
        'defaultOnEmptyString' => false
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
     * Merge options with default options.
     * @param $options
     * @param $key
     * @return array
     */
    private function mergeOptions($options, $key)
    {
        $options = array_merge($this->defaultOptions, $options);
        $options['throwMessage'] = str_replace('{{param}}', $key, $options['throwMessage']);
        $options['key'] = $key;

        return $options;
    }

    /**
     * Return a failure
     * @param $options
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     * @return void|\Exception
     */
    private function failure($options)
    {
        $throw = $options['throw'];

        if (is_callable($throw)) {
            $throw();
        } else if (!is_null($throw)) {
            throw $throw;
        } else if ($options['throwClass'] !== null) {
            throw new $options['throwClass']($options['throwMessage']);
        } else {
            throw new InvalidArgumentException($options['throwMessage'], $options['key']);
        }
    }

    /**
     * Return a failure or default
     * @param $options
     * @return mixed
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    private function failureNotExists($options)
    {
        if (is_null($options['throw']) && $options['defaultOnNotExists'])
            return $options['default'];

        return $this->failure($options);
    }

    /** @inheritDoc */
    public function getParam($key, $options = [])
    {
        $options = $this->mergeOptions($options, $key);

        if (!$this->collection->has($key)) {
            return $this->failureNotExists($options);
        }

        $value = $this->collection->get($key);
        if ($value === null || ($value === '' && $options['defaultOnEmptyString'])) {
            return $this->failureNotExists($options);
        }

        return $value;
    }

    /**
     * @inheritdoc
     */
    public function getInt($key, $options = [])
    {
        $options = $this->mergeOptions($options, $key);

        if (!$this->collection->has($key))
            return $this->failureNotExists($options);

        $value = $this->collection->get($key);

        // Treat empty string as null for integer types
        // with the default options this will return the default value of null
        if ($value === null || $value === '')
            return $this->failureNotExists($options);

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
        $options = $this->mergeOptions($options, $key);

        if (!$this->collection->has($key))
            return $this->failureNotExists($options);

        $value = $this->collection->get($key);

        // Treat empty string as null for double types
        // with the default options this will return the default value of null
        if ($value === null || $value === '')
            return $this->failureNotExists($options);

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
        $options = $this->mergeOptions($options, $key);

        if (!$this->collection->has($key))
            return $this->failureNotExists($options);

        $value = $this->collection->get($key);

        if ($value === null || ($value === '' && $options['defaultOnEmptyString']) ) {
            return $this->failureNotExists($options);
        }

        // Validate the parameter
        if (!v::stringType()->addRules($options['rules'])->validate($value)) {
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
        $options = $this->mergeOptions($options, $key);

        if (!$this->collection->has($key))
            return $this->failureNotExists($options);

        $value = $this->collection->get($key);

        if ($value == null)
            return $this->failureNotExists($options);

        if ($value instanceof Carbon)
            return $value;

        // Validate the parameter
        if (!v::date($options['dateFormat'])->addRules($options['rules'])->validate($value)) {
            return $this->failure($options);
        } else {
            try {
                return Carbon::createFromFormat($options['dateFormat'], $value);
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
        $options = $this->mergeOptions($options, $key);

        if (!$this->collection->has($key))
            return $this->failureNotExists($options);

        $value = $this->collection->get($key);

        if ($value === null)
            return $this->failureNotExists($options);

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
        $options = $this->mergeOptions($options, $key);

        if (!$this->collection->has($key))
            return $this->failureNotExists($options);

        $value = $this->collection->get($key);

        if ($value === null)
            return $this->failureNotExists($options);

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
    public function getCheckbox($key, $options = [])
    {
        $options = $this->mergeOptions($options, $key);

        if (!$this->collection->has($key)) {
            $return = ($options['default'] != null) ? $options['default'] : false;
            return $options['checkboxReturnInteger'] ? ($return ? 1 : 0) : $return;
        }

        $value = $this->collection->get($key);

        // Validate the parameter
        $return = ($value === 'on' || $value === 1 || $value === '1' || $value === 'true' || $value === true);
        return $options['checkboxReturnInteger'] ? ($return ? 1 : 0) : $return;
    }

    /**
     * @inheritdoc
     */
    public function hasParam($key)
    {
        return $this->collection->has($key);
    }
}