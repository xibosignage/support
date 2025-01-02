<?php
/*
 * Xibo Signage Ltd - http://www.xibosignage.com
 * Copyright (c) 2018 Xibo Signage Ltd
 * (RespectValidator.php)
 */


namespace Xibo\Support\Validator;

use Respect\Validation\Factory;
use Respect\Validation\Rules\AllOf;
use Respect\Validation\Rules\IntVal;
use Respect\Validation\Rules\NumericVal;
use Respect\Validation\Rules\StringType;

class RespectValidator implements ValidatorInterface
{
    /**
     * @inheritdoc
     */
    public function int($value, $rules = [])
    {
        $validator = new AllOf(new IntVal());
        $this->addRules($validator, $rules);
        return $validator->validate($value);
    }

    /**
     * @inheritdoc
     */
    public function double($value, $rules = [])
    {
        $validator = new AllOf(new NumericVal());
        $this->addRules($validator, $rules);
        return $validator->validate($value);
    }

    /**
     * @inheritdoc
     */
    public function string($value, $rules = [])
    {
        $validator = new AllOf(new StringType());
        $this->addRules($validator, $rules);
        return $validator->validate($value);
    }

    private function addRules(AllOf $validator, $rules): AllOf
    {
        foreach ($rules as $ruleName => $arguments) {
            $validator->addRule(Factory::getDefaultInstance()->rule($ruleName, $arguments));
        }
        return $validator;
    }
}
