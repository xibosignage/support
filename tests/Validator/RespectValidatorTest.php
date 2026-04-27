<?php

namespace Xibo\Support\Tests\Validator;

use PHPUnit\Framework\TestCase;
use Xibo\Support\Validator\RespectValidator;

class RespectValidatorTest extends TestCase
{
    private RespectValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new RespectValidator();
    }

    // ------------------------------------------------------------------
    // int
    // ------------------------------------------------------------------

    public function testIntAcceptsNumericString(): void
    {
        $this->assertTrue($this->validator->int('123'));
    }

    public function testIntAcceptsNegativeString(): void
    {
        $this->assertTrue($this->validator->int('-5'));
    }

    public function testIntAcceptsZeroString(): void
    {
        $this->assertTrue($this->validator->int('0'));
    }

    public function testIntRejectsAlphaString(): void
    {
        $this->assertFalse($this->validator->int('abc'));
    }

    public function testIntRejectsFloatString(): void
    {
        $this->assertFalse($this->validator->int('1.5'));
    }

    public function testIntRejectsNull(): void
    {
        $this->assertFalse($this->validator->int(null));
    }

    public function testIntRejectsEmptyString(): void
    {
        $this->assertFalse($this->validator->int(''));
    }

    public function testIntAppliesCustomRulePasses(): void
    {
        $this->assertTrue($this->validator->int('15', ['Min' => [10]]));
    }

    public function testIntAppliesCustomRuleFails(): void
    {
        $this->assertFalse($this->validator->int('5', ['Min' => [10]]));
    }

    // ------------------------------------------------------------------
    // double
    // ------------------------------------------------------------------

    public function testDoubleAcceptsFloatString(): void
    {
        $this->assertTrue($this->validator->double('3.14'));
    }

    public function testDoubleAcceptsIntegerString(): void
    {
        $this->assertTrue($this->validator->double('42'));
    }

    public function testDoubleAcceptsScientificNotation(): void
    {
        $this->assertTrue($this->validator->double('314e-2'));
    }

    public function testDoubleRejectsAlphaString(): void
    {
        $this->assertFalse($this->validator->double('abc'));
    }

    public function testDoubleRejectsNull(): void
    {
        $this->assertFalse($this->validator->double(null));
    }

    public function testDoubleAppliesCustomRulePasses(): void
    {
        $this->assertTrue($this->validator->double('5.5', ['Min' => [5]]));
    }

    public function testDoubleAppliesCustomRuleFails(): void
    {
        $this->assertFalse($this->validator->double('3.0', ['Min' => [5]]));
    }

    // ------------------------------------------------------------------
    // string
    // ------------------------------------------------------------------

    public function testStringAcceptsRegularString(): void
    {
        $this->assertTrue($this->validator->string('hello'));
    }

    public function testStringAcceptsEmptyString(): void
    {
        // StringType accepts empty string — no length constraint by default
        $this->assertTrue($this->validator->string(''));
    }

    public function testStringRejectsInteger(): void
    {
        $this->assertFalse($this->validator->string(123));
    }

    public function testStringRejectsNull(): void
    {
        $this->assertFalse($this->validator->string(null));
    }

    public function testStringAppliesLengthRulePasses(): void
    {
        $this->assertTrue($this->validator->string('hello', ['Length' => [3, 10]]));
    }

    public function testStringAppliesLengthRuleFails(): void
    {
        $this->assertFalse($this->validator->string('hi', ['Length' => [3, 10]]));
    }
}
