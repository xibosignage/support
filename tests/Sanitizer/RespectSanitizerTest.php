<?php

namespace Xibo\Support\Tests\Sanitizer;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Sanitizer\RespectSanitizer;
use Xibo\Support\Tests\Fixtures\CustomThrowException;

class RespectSanitizerTest extends TestCase
{
    private function sanitizer(array $data): RespectSanitizer
    {
        return (new RespectSanitizer())->setCollection($data);
    }

    // ------------------------------------------------------------------
    // setCollection / setDefaultOptions / hasParam
    // ------------------------------------------------------------------

    public function testSetCollectionAcceptsArray(): void
    {
        $s = (new RespectSanitizer())->setCollection(['foo' => 'bar']);
        $this->assertTrue($s->hasParam('foo'));
    }

    public function testSetCollectionAcceptsCollection(): void
    {
        $s = (new RespectSanitizer())->setCollection(new Collection(['foo' => 'bar']));
        $this->assertTrue($s->hasParam('foo'));
    }

    public function testSetCollectionReturnsSelf(): void
    {
        $s = new RespectSanitizer();
        $this->assertSame($s, $s->setCollection([]));
    }

    public function testSetDefaultOptionsMergesOptions(): void
    {
        $s = $this->sanitizer(['x' => '']);
        $s->setDefaultOptions(['defaultOnEmptyString' => true]);
        $this->assertNull($s->getString('x'));
    }

    public function testHasParamReturnsTrueWhenPresent(): void
    {
        $this->assertTrue($this->sanitizer(['a' => 1])->hasParam('a'));
    }

    public function testHasParamReturnsFalseWhenMissing(): void
    {
        $this->assertFalse($this->sanitizer([])->hasParam('missing'));
    }

    // ------------------------------------------------------------------
    // getParam
    // ------------------------------------------------------------------

    public function testGetParamReturnsRawStringValue(): void
    {
        $this->assertSame('hello', $this->sanitizer(['k' => 'hello'])->getParam('k'));
    }

    public function testGetParamReturnsRawArrayValue(): void
    {
        $val = [1, 2, 3];
        $this->assertSame($val, $this->sanitizer(['k' => $val])->getParam('k'));
    }

    public function testGetParamReturnsDefaultWhenMissing(): void
    {
        $this->assertNull($this->sanitizer([])->getParam('missing'));
    }

    public function testGetParamReturnsDefaultWhenNull(): void
    {
        $this->assertNull($this->sanitizer(['k' => null])->getParam('k'));
    }

    public function testGetParamReturnsEmptyStringByDefault(): void
    {
        $this->assertSame('', $this->sanitizer(['k' => ''])->getParam('k'));
    }

    public function testGetParamReturnsDefaultOnEmptyStringWhenFlagSet(): void
    {
        $this->assertNull(
            $this->sanitizer(['k' => ''])->getParam('k', ['defaultOnEmptyString' => true])
        );
    }

    public function testGetParamThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->sanitizer([])->getParam('missing', ['defaultOnNotExists' => false]);
    }

    public function testGetParamRunsCallableThrow(): void
    {
        $called = false;
        $this->sanitizer([])->getParam('missing', [
            'throw' => function () use (&$called) { $called = true; }
        ]);
        $this->assertTrue($called);
    }

    public function testGetParamThrowMessageInterpolatesParam(): void
    {
        try {
            $this->sanitizer([])->getParam('myKey', [
                'defaultOnNotExists' => false,
                'throwMessage' => 'Missing {{param}}',
            ]);
            $this->fail('Expected exception not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('myKey', $e->getMessage());
        }
    }

    // ------------------------------------------------------------------
    // getInt
    // ------------------------------------------------------------------

    public function testGetIntParsesStringInteger(): void
    {
        $this->assertSame(42, $this->sanitizer(['n' => '42'])->getInt('n'));
    }

    public function testGetIntParsesNativeInteger(): void
    {
        $this->assertSame(7, $this->sanitizer(['n' => 7])->getInt('n'));
    }

    public function testGetIntParsesNegativeInteger(): void
    {
        $this->assertSame(-5, $this->sanitizer(['n' => '-5'])->getInt('n'));
    }

    public function testGetIntParsesZeroString(): void
    {
        $this->assertSame(0, $this->sanitizer(['n' => '0'])->getInt('n'));
    }

    public function testGetIntReturnsNullWhenMissing(): void
    {
        $this->assertNull($this->sanitizer([])->getInt('n'));
    }

    public function testGetIntReturnsNullWhenValueIsNull(): void
    {
        $this->assertNull($this->sanitizer(['n' => null])->getInt('n'));
    }

    public function testGetIntReturnsNullOnEmptyString(): void
    {
        $this->assertNull($this->sanitizer(['n' => ''])->getInt('n'));
    }

    public function testGetIntThrowsOnFloatString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->sanitizer(['n' => '3.14'])->getInt('n', ['defaultOnNotExists' => false]);
    }

    public function testGetIntThrowsOnAlphaString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->sanitizer(['n' => 'abc'])->getInt('n', ['defaultOnNotExists' => false]);
    }

    public function testGetIntThrowsWhenDefaultOnNotExistsFalseAndMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->sanitizer([])->getInt('n', ['defaultOnNotExists' => false]);
    }

    public function testGetIntAppliesCustomRulePasses(): void
    {
        $result = $this->sanitizer(['n' => '11'])->getInt('n', ['rules' => ['Min' => [10]]]);
        $this->assertSame(11, $result);
    }

    public function testGetIntAppliesCustomRuleFails(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->sanitizer(['n' => '5'])->getInt('n', [
            'defaultOnNotExists' => false,
            'rules' => ['Min' => [10]],
        ]);
    }

    public function testGetIntUsesThrowClass(): void
    {
        $this->expectException(CustomThrowException::class);
        $this->sanitizer(['n' => 'bad'])->getInt('n', ['throwClass' => CustomThrowException::class]);
    }

    public function testGetIntInterpolatesThrowMessage(): void
    {
        try {
            $this->sanitizer(['myParam' => 'bad'])->getInt('myParam', [
                'throwMessage' => 'Bad value for {{param}}',
            ]);
            $this->fail('Expected exception');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('myParam', $e->getMessage());
        }
    }

    public function testGetIntCallableThrowExecuted(): void
    {
        $called = false;
        $this->sanitizer(['n' => 'bad'])->getInt('n', [
            'throw' => function () use (&$called) { $called = true; }
        ]);
        $this->assertTrue($called);
    }

    // ------------------------------------------------------------------
    // getDouble
    // ------------------------------------------------------------------

    public function testGetDoubleParsesFloatString(): void
    {
        $this->assertSame(3.14, $this->sanitizer(['d' => '3.14'])->getDouble('d'));
    }

    public function testGetDoubleParseIntegerString(): void
    {
        $this->assertSame(42.0, $this->sanitizer(['d' => '42'])->getDouble('d'));
    }

    public function testGetDoubleReturnsNullOnEmptyString(): void
    {
        $this->assertNull($this->sanitizer(['d' => ''])->getDouble('d'));
    }

    public function testGetDoubleThrowsOnAlphaString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->sanitizer(['d' => 'abc'])->getDouble('d', ['defaultOnNotExists' => false]);
    }

    public function testGetDoubleReturnsNullWhenMissing(): void
    {
        $this->assertNull($this->sanitizer([])->getDouble('d'));
    }

    public function testGetDoubleCustomRulePasses(): void
    {
        $result = $this->sanitizer(['d' => '5.5'])->getDouble('d', ['rules' => ['Min' => [5]]]);
        $this->assertSame(5.5, $result);
    }

    public function testGetDoubleCustomRuleFails(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->sanitizer(['d' => '3.0'])->getDouble('d', [
            'defaultOnNotExists' => false,
            'rules' => ['Min' => [5]],
        ]);
    }

    public function testGetDoubleThrowsWhenMissingAndDefaultOnNotExistsFalse(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->sanitizer([])->getDouble('d', ['defaultOnNotExists' => false]);
    }

    public function testGetDoubleUsesThrowClass(): void
    {
        $this->expectException(CustomThrowException::class);
        $this->sanitizer(['d' => 'bad'])->getDouble('d', ['throwClass' => CustomThrowException::class]);
    }

    // ------------------------------------------------------------------
    // getString
    // ------------------------------------------------------------------

    public function testGetStringReturnsPlainString(): void
    {
        $this->assertSame('hello world', $this->sanitizer(['s' => 'hello world'])->getString('s'));
    }

    public function testGetStringStripsHtmlTags(): void
    {
        $this->assertSame('alert(1)', $this->sanitizer(['s' => '<script>alert(1)</script>'])->getString('s'));
    }

    public function testGetStringPreservesAmpersand(): void
    {
        // strip_tags does NOT encode entities — Tom & Jerry stays as-is
        $this->assertSame('Tom & Jerry', $this->sanitizer(['s' => 'Tom & Jerry'])->getString('s'));
    }

    public function testGetStringPreservesQuotes(): void
    {
        $this->assertSame('say "hi"', $this->sanitizer(['s' => 'say "hi"'])->getString('s'));
    }

    public function testGetStringReturnsEmptyStringByDefault(): void
    {
        $this->assertSame('', $this->sanitizer(['s' => ''])->getString('s'));
    }

    public function testGetStringReturnsDefaultOnEmptyStringWhenFlagSet(): void
    {
        $this->assertNull(
            $this->sanitizer(['s' => ''])->getString('s', ['defaultOnEmptyString' => true])
        );
    }

    public function testGetStringReturnsNullWhenNull(): void
    {
        $this->assertNull($this->sanitizer(['s' => null])->getString('s'));
    }

    public function testGetStringReturnsNullWhenMissing(): void
    {
        $this->assertNull($this->sanitizer([])->getString('s'));
    }

    public function testGetStringThrowsOnNonString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->sanitizer(['s' => 123])->getString('s', ['defaultOnNotExists' => false]);
    }

    public function testGetStringCustomLengthRulePasses(): void
    {
        $result = $this->sanitizer(['s' => 'hello'])->getString('s', ['rules' => ['Length' => [3, 10]]]);
        $this->assertSame('hello', $result);
    }

    public function testGetStringCustomLengthRuleFails(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->sanitizer(['s' => 'hi'])->getString('s', [
            'defaultOnNotExists' => false,
            'rules' => ['Length' => [3, 10]],
        ]);
    }

    public function testGetStringUsesThrowClass(): void
    {
        $this->expectException(CustomThrowException::class);
        $this->sanitizer(['s' => 123])->getString('s', ['throwClass' => CustomThrowException::class]);
    }

    public function testGetStringInterpolatesThrowMessage(): void
    {
        try {
            $this->sanitizer(['myStr' => 123])->getString('myStr', [
                'throwMessage' => 'Bad {{param}}',
            ]);
            $this->fail('Expected exception');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('myStr', $e->getMessage());
        }
    }

    // ------------------------------------------------------------------
    // getDate
    // ------------------------------------------------------------------

    public function testGetDateReturnsCarbonInstanceAsIs(): void
    {
        $carbon = Carbon::parse('2024-06-01 12:00:00');
        $result = $this->sanitizer(['d' => $carbon])->getDate('d');
        $this->assertSame($carbon, $result);
    }

    public function testGetDateParsesValidDateString(): void
    {
        $result = $this->sanitizer(['d' => '2024-01-15 10:00:00'])->getDate('d');
        $this->assertInstanceOf(Carbon::class, $result);
        $this->assertSame('2024-01-15', $result->toDateString());
    }

    public function testGetDateUsesCustomDateFormat(): void
    {
        $result = $this->sanitizer(['d' => '2024-03-20'])
            ->getDate('d', ['dateFormat' => 'Y-m-d']);
        $this->assertInstanceOf(Carbon::class, $result);
        $this->assertSame('2024-03-20', $result->toDateString());
    }

    public function testGetDateThrowsOnMalformedDate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->sanitizer(['d' => 'not-a-date'])->getDate('d', ['defaultOnNotExists' => false]);
    }

    public function testGetDateReturnsDefaultOnEmptyString(): void
    {
        // getDate uses loose == null, so empty string is treated as missing
        $this->assertNull($this->sanitizer(['d' => ''])->getDate('d'));
    }

    public function testGetDateReturnsNullWhenNull(): void
    {
        $this->assertNull($this->sanitizer(['d' => null])->getDate('d'));
    }

    public function testGetDateReturnsNullWhenMissing(): void
    {
        $this->assertNull($this->sanitizer([])->getDate('d'));
    }

    public function testGetDateUsesThrowClass(): void
    {
        $this->expectException(CustomThrowException::class);
        $this->sanitizer(['d' => 'bad'])->getDate('d', ['throwClass' => CustomThrowException::class]);
    }

    public function testGetDateThrowsOnWrongFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        // Date is valid but format doesn't match
        $this->sanitizer(['d' => '01/15/2024'])->getDate('d', [
            'defaultOnNotExists' => false,
            'dateFormat' => 'Y-m-d H:i:s',
        ]);
    }

    // ------------------------------------------------------------------
    // getArray
    // ------------------------------------------------------------------

    public function testGetArrayReturnsArrayUnchanged(): void
    {
        $arr = ['a' => 1, 'b' => 2];
        $this->assertSame($arr, $this->sanitizer(['arr' => $arr])->getArray('arr'));
    }

    public function testGetArrayReturnsEmptyArray(): void
    {
        $this->assertSame([], $this->sanitizer(['arr' => []])->getArray('arr'));
    }

    public function testGetArrayThrowsOnNonArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->sanitizer(['arr' => 'nope'])->getArray('arr', ['defaultOnNotExists' => false]);
    }

    public function testGetArrayReturnsNullWhenValueIsNull(): void
    {
        $this->assertNull($this->sanitizer(['arr' => null])->getArray('arr'));
    }

    public function testGetArrayReturnsNullWhenMissing(): void
    {
        $this->assertNull($this->sanitizer([])->getArray('arr'));
    }

    public function testGetArrayPreservesNestedArrays(): void
    {
        $arr = ['x' => ['y' => ['z' => 99]]];
        $this->assertSame($arr, $this->sanitizer(['arr' => $arr])->getArray('arr'));
    }

    // ------------------------------------------------------------------
    // getIntArray
    // ------------------------------------------------------------------

    public function testGetIntArrayConvertsStringIntegers(): void
    {
        $this->assertSame([1, 2, 3], $this->sanitizer(['arr' => ['1', '2', '3']])->getIntArray('arr'));
    }

    public function testGetIntArrayCoercesNonNumericToZero(): void
    {
        // intval('abc') === 0 — document this behavior
        $this->assertSame([1, 0], $this->sanitizer(['arr' => ['1', 'abc']])->getIntArray('arr'));
    }

    public function testGetIntArrayThrowsOnNonArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->sanitizer(['arr' => 'nope'])->getIntArray('arr', ['defaultOnNotExists' => false]);
    }

    public function testGetIntArrayReturnsNullWhenMissing(): void
    {
        $this->assertNull($this->sanitizer([])->getIntArray('arr'));
    }

    public function testGetIntArrayReturnsNullWhenNull(): void
    {
        $this->assertNull($this->sanitizer(['arr' => null])->getIntArray('arr'));
    }

    // ------------------------------------------------------------------
    // getCheckbox
    // ------------------------------------------------------------------

    /** @dataProvider checkboxTruthyProvider */
    public function testGetCheckboxReturnsTrueForTruthyValues(mixed $value): void
    {
        $this->assertTrue($this->sanitizer(['cb' => $value])->getCheckbox('cb'));
    }

    public static function checkboxTruthyProvider(): array
    {
        return [['on'], [1], ['1'], ['true'], [true]];
    }

    /** @dataProvider checkboxFalsyProvider */
    public function testGetCheckboxReturnsFalseForFalsyValues(mixed $value): void
    {
        $this->assertFalse($this->sanitizer(['cb' => $value])->getCheckbox('cb'));
    }

    public static function checkboxFalsyProvider(): array
    {
        return [['off'], ['0'], [0], [''], [false], ['ON']]; // 'ON' is case-sensitive
    }

    public function testGetCheckboxReturnsFalseWhenMissingWithNullDefault(): void
    {
        $this->assertFalse($this->sanitizer([])->getCheckbox('cb'));
    }

    public function testGetCheckboxReturnsTrueWhenMissingWithTrueDefault(): void
    {
        // $options['default'] != null: true != null is true so default is used
        $this->assertTrue($this->sanitizer([])->getCheckbox('cb', ['default' => true]));
    }

    public function testGetCheckboxReturnIntegerOneWhenFlagSet(): void
    {
        $this->assertSame(1, $this->sanitizer(['cb' => 'on'])->getCheckbox('cb', ['checkboxReturnInteger' => true]));
    }

    public function testGetCheckboxReturnIntegerZeroWhenFlagSet(): void
    {
        $this->assertSame(0, $this->sanitizer(['cb' => ''])->getCheckbox('cb', ['checkboxReturnInteger' => true]));
    }

    public function testGetCheckboxNeverThrowsEvenWhenMissing(): void
    {
        // No exception even with defaultOnNotExists=false
        $result = $this->sanitizer([])->getCheckbox('cb', ['defaultOnNotExists' => false]);
        $this->assertFalse($result);
    }

    // ------------------------------------------------------------------
    // getHtml
    // ------------------------------------------------------------------

    public function testGetHtmlPreservesSafeTags(): void
    {
        $result = $this->sanitizer(['h' => '<p>hello</p>'])->getHtml('h');
        $this->assertStringContainsString('hello', $result);
        $this->assertStringContainsString('<p>', $result);
    }

    public function testGetHtmlStripsScriptTags(): void
    {
        $result = $this->sanitizer(['h' => '<script>evil()</script>'])->getHtml('h');
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringNotContainsString('evil()', $result);
    }

    public function testGetHtmlStripsJavascriptHref(): void
    {
        $result = $this->sanitizer(['h' => '<a href="javascript:void(0)">click</a>'])->getHtml('h');
        $this->assertStringNotContainsString('javascript:', $result);
    }

    public function testGetHtmlReturnsEmptyStringByDefault(): void
    {
        $this->assertSame('', $this->sanitizer(['h' => ''])->getHtml('h'));
    }

    public function testGetHtmlReturnsDefaultOnEmptyStringWhenFlagSet(): void
    {
        $this->assertNull(
            $this->sanitizer(['h' => ''])->getHtml('h', ['defaultOnEmptyString' => true])
        );
    }

    public function testGetHtmlReturnsNullWhenNull(): void
    {
        $this->assertNull($this->sanitizer(['h' => null])->getHtml('h'));
    }

    public function testGetHtmlReturnsNullWhenMissing(): void
    {
        $this->assertNull($this->sanitizer([])->getHtml('h'));
    }

    public function testGetHtmlUsesCustomHtmlSanitizerConfig(): void
    {
        $config = (new \Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig())
            ->blockElement('p');
        $result = $this->sanitizer(['h' => '<p>test</p>'])->getHtml('h', ['htmlSanitizerConfig' => $config]);
        // With a custom config that blocks p, the p tag should be stripped
        $this->assertStringNotContainsString('<p>', $result);
    }

    public function testGetHtmlUsesCustomHtmlSanitizerInstance(): void
    {
        $mockSanitizer = $this->createMock(\Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface::class);
        $mockSanitizer->expects($this->once())
            ->method('sanitizeFor')
            ->with('body', '<b>hi</b>')
            ->willReturn('<b>hi</b>');

        $this->sanitizer(['h' => '<b>hi</b>'])->getHtml('h', ['htmlSanitizer' => $mockSanitizer]);
    }

    public function testGetHtmlUsesCustomSanitizerForContext(): void
    {
        $mockSanitizer = $this->createMock(\Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface::class);
        $mockSanitizer->expects($this->once())
            ->method('sanitizeFor')
            ->with('div', $this->anything())
            ->willReturn('');

        $this->sanitizer(['h' => 'text'])->getHtml('h', [
            'htmlSanitizer' => $mockSanitizer,
            'htmlSanitizerFor' => 'div',
        ]);
    }

    public function testGetHtmlUsesThrowClassOnNonString(): void
    {
        $this->expectException(CustomThrowException::class);
        $this->sanitizer(['h' => 123])->getHtml('h', ['throwClass' => CustomThrowException::class]);
    }
}
