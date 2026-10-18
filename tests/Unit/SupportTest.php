<?php

namespace Tests\Unit;

use App\Enums\Recurrence;
use App\Support\CsvWriter;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SupportTest extends TestCase
{
    public static function formulaCells(): array
    {
        return [
            ['=SUM(A1:A9)', "'=SUM(A1:A9)"],
            ['+44 20', "'+44 20"],
            ['-1+1', "'-1+1"],
            ['@cmd', "'@cmd"],
            ["\tTabbed", "'\tTabbed"],
            ['Counts matched', 'Counts matched'],
            ['142,850', '142,850'],
            [null, ''],
            [true, 'Yes'],
        ];
    }

    #[DataProvider('formulaCells')]
    public function test_csv_cells_that_could_run_as_formulas_are_neutralised(mixed $input, string $expected): void
    {
        $this->assertSame($expected, CsvWriter::sanitise($input));
    }

    public function test_recurrence_rules(): void
    {
        $monday = CarbonImmutable::parse('2026-10-12');
        $saturday = CarbonImmutable::parse('2026-10-17');

        $this->assertTrue(Recurrence::Daily->appliesOn($saturday));
        $this->assertTrue(Recurrence::Weekdays->appliesOn($monday));
        $this->assertFalse(Recurrence::Weekdays->appliesOn($saturday));
        $this->assertTrue(Recurrence::Weekly->appliesOn($saturday, [6, 7]));
        $this->assertFalse(Recurrence::Weekly->appliesOn($monday, [6, 7]));
        $this->assertTrue(Recurrence::Monthly->appliesOn(CarbonImmutable::parse('2026-02-28'), [31]));
        $this->assertFalse(Recurrence::Monthly->appliesOn(CarbonImmutable::parse('2026-02-27'), [31]));
        $this->assertFalse(Recurrence::AdHoc->appliesOn($monday));
        $this->assertSame('Weekly on Mon and Fri', Recurrence::Weekly->describe([5, 1]));
    }
}
