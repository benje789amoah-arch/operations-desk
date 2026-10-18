<?php

namespace App\Rules;

use Closure;
use DateTimeImmutable;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects an end date that is more than $maxDays after the start date field.
 */
class MaxDateRange implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function __construct(private readonly string $startField, private readonly int $maxDays) {}

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $start = DateTimeImmutable::createFromFormat('!Y-m-d', (string) data_get($this->data, $this->startField));
        $end = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);

        if ($start && $end && $start->diff($end)->days > $this->maxDays) {
            $fail("Reports can cover at most {$this->maxDays} days. Narrow the date range.");
        }
    }
}
