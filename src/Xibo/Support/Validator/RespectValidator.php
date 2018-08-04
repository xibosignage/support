<?php
/*
 * Xibo Signage Ltd - http://www.xibosignage.com
 * Copyright (c) 2018 Xibo Signage Ltd
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
        return v::intVal()->addRules($rules)->validate($value);
    }

    /**
     * @inheritdoc
     */
    public function double($value, $rules = [])
    {
        return v::numeric()->addRules($rules)->validate($value);
    }

    /**
     * @inheritdoc
     */
    public function string($value, $rules = [])
    {
        return v::stringType()->addRules($rules)->validate($value);
    }
}