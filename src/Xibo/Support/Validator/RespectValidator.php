<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2017 Spring Signage Ltd
 * (RespectValidator.php)
 */


namespace Xibo\Support\Validator;

use Respect\Validation\Validator as v;

class RespectValidator implements ValidatorInterface
{
    /**
     * @inheritdoc
     */
    public function int($value, $rules = [])
    {
        return v::intVal()->addRules($rules['rules'])->validate($value);
    }

    /**
     * @inheritdoc
     */
    public function double($value, $rules = [])
    {
        return v::numeric()->addRules($rules['rules'])->validate($value);
    }

    /**
     * @inheritdoc
     */
    public function string($value, $rules = [])
    {
        return v::stringType()->addRules($rules['rules'])->validate($value);
    }
}