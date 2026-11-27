<?php

namespace Tests\Unit;

use App\Invoicing\NumberFormat;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NumberFormatTest extends TestCase
{
    public function test_it_renders_the_counter_and_the_date(): void
    {
        $date = new DateTimeImmutable('2026-03-09');

        $this->assertSame('FV/007/03/2026', NumberFormat::render('FV/{NNN}/{MM}/{YYYY}', 7, $date));
        $this->assertSame('7/26', NumberFormat::render('{N}/{YY}', 7, $date));
        $this->assertSame('FV-0042', NumberFormat::render('FV-{NNNN}', 42, $date));
        $this->assertSame('FV/1234', NumberFormat::render('FV/{NN}', 1234, $date), 'The counter is never cut.');
    }

    #[DataProvider('formats')]
    public function test_it_tells_usable_patterns_from_the_rest(string $format, bool $valid): void
    {
        $this->assertSame($valid, NumberFormat::isValid($format));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function formats(): array
    {
        return [
            'usual' => ['FV/{NNN}/{YYYY}', true],
            'plain counter' => ['{N}', true],
            'no counter' => ['FV/{YYYY}', false],
            'unknown token' => ['FV/{NNN}/{DD}', false],
            'stray brace' => ['FV/{NNN}/{', false],
        ];
    }
}
