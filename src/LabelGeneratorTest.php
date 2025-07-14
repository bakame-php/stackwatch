<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LabelGeneratorTest extends TestCase
{
    /**
     * @param non-empty-string $input
     */
    #[Test]
    #[DataProvider('provideValidLabels')]
    public function it_will_sanitize_a_label(string $input, string $expected): void
    {
        self::assertSame($expected, LabelGenerator::sanitize($input));
    }

    /**
     * @return iterable<string, array{input:non-empty-string, expected:non-empty-string}>
     */
    public static function provideValidLabels(): iterable
    {
        yield 'uppercased label converted to lowercased label' => [
            'input' => 'Average',
            'expected' => 'average',
        ];

        yield 'lowercased label' => [
            'input' => 'lowercase',
            'expected' => 'lowercase',
        ];

        yield 'label with underscore' => [
            'input' => 'lower_case',
            'expected' => 'lower_case',
        ];

        yield 'label with dot' => [
            'input' => 'LOWER.case',
            'expected' => 'lower.case',
        ];

        yield 'label with dot and underscore' => [
            'input' => 'LOWER.ca_Se',
            'expected' => 'lower.ca_se',
        ];
    }

    #[Test]
    #[DataProvider('provideInvalidLabels')]
    public function it_fails_to_sanitize_the_label(string $input): void
    {
        $this->expectException(InvalidArgument::class);

        LabelGenerator::sanitize($input);
    }

    /**
     * @return iterable<string, array{input: string}>
     */
    public static function provideInvalidLabels(): iterable
    {
        yield 'empty string' => ['input' => ''];
        yield 'long empty string' => ['input' => '       '];
        yield 'starts with an underscore' => ['input' => '_label'];
        yield 'starts with an dot' => ['input' => '.label'];
        yield 'ends with an underscore' => ['input' => 'label_'];
        yield 'ends with an dot' => ['input' => 'label.'];
        yield 'contains at least on double underscore' => ['input' => 'lab__el'];
        yield 'econtains at least on double dot' => ['input' => 'lab..el'];
    }

    public function it_will_throw_an_exception_if_the_label_length_is_lower_than_1(): void
    {
        $this->expectException(InvalidArgument::class);

        new LabelGenerator(0); /* @phpstan-ignore-line */
    }
}
