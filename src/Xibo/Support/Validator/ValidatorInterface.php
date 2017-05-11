<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2017 Spring Signage Ltd
 * (ValidatorInterface.php)
 */


namespace Xibo\Support\Validator;

interface ValidatorInterface
{
    /**
     * @param string $value The value
     * @param array $rules Rules to apply to the operation
     * @return bool
     */
    public function int($value, $rules = []);

    /**
     * @param string $value The value
     * @param array $rules Rules to apply to the operation
     * @return bool
     */
    public function double($value, $rules = []);

    /**
     * @param string $value The value
     * @param array $rules Rules to apply to the operation
     * @return bool
     */
    public function string($value, $rules = []);
}