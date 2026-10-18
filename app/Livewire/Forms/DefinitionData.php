<?php

namespace App\Livewire\Forms;

use App\Enums\Priority;
use App\Enums\Recurrence;
use App\Models\ActivityDefinition;
use App\Support\OpsClock;
use Closure;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * Validation and data mapping for creating or editing an activity definition.
 */
class DefinitionData extends Form
{
    public string $title = '';

    public string $description = '';

    public string $category_id = '';

    public string $priority = 'normal';

    public string $recurrence = 'daily';

    /** @var array<int, string> */
    public array $weekdays = [];

    public string $monthDay = '1';

    public string $scheduled_time = '';

    public string $due_time = '';

    public string $default_assignee_id = '';

    public string $starts_on = '';

    public string $ends_on = '';

    public function fillFrom(?ActivityDefinition $definition): void
    {
        if (! $definition) {
            $this->starts_on = OpsClock::today()->format('Y-m-d');

            return;
        }

        $this->title = $definition->title;
        $this->description = (string) $definition->description;
        $this->category_id = (string) ($definition->category_id ?? '');
        $this->priority = $definition->priority->value;
        $this->recurrence = $definition->recurrence->value;
        $this->weekdays = $definition->recurrence === Recurrence::Weekly ? array_map('strval', $definition->recurrence_days ?? []) : [];
        $this->monthDay = $definition->recurrence === Recurrence::Monthly ? (string) ($definition->recurrence_days[0] ?? 1) : '1';
        $this->scheduled_time = $definition->scheduled_time ? substr($definition->scheduled_time, 0, 5) : '';
        $this->due_time = $definition->due_time ? substr($definition->due_time, 0, 5) : '';
        $this->default_assignee_id = (string) ($definition->default_assignee_id ?? '');
        $this->starts_on = $definition->starts_on->format('Y-m-d');
        $this->ends_on = $definition->ends_on?->format('Y-m-d') ?? '';
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:5', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['nullable', Rule::exists('categories', 'id')],
            'priority' => ['required', Rule::enum(Priority::class)],
            'recurrence' => ['required', Rule::enum(Recurrence::class)],
            'weekdays' => [Rule::requiredIf($this->recurrence === Recurrence::Weekly->value), 'array'],
            'weekdays.*' => ['integer', 'between:1,7'],
            'monthDay' => [Rule::requiredIf($this->recurrence === Recurrence::Monthly->value), 'integer', 'between:1,31'],
            'scheduled_time' => ['nullable', 'date_format:H:i'],
            'due_time' => ['nullable', 'date_format:H:i', function (string $attribute, mixed $value, Closure $fail) {
                if (filled($value) && filled($this->scheduled_time) && $value <= $this->scheduled_time) {
                    $fail('The due time must be later than the scheduled time.');
                }
            }],
            'default_assignee_id' => ['nullable', Rule::exists('users', 'id')->where('is_active', true)],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', function (string $attribute, mixed $value, Closure $fail) {
                if (filled($value) && filled($this->starts_on) && $value < $this->starts_on) {
                    $fail('The end date cannot be before the start date.');
                }
            }],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'title.required' => 'Give the activity a clear title, e.g. "Daily SMS count in comparison to SMS count from logs".',
            'weekdays.required' => 'Choose at least one day of the week.',
            'default_assignee_id.exists' => 'Choose an active team member.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        $recurrence = Recurrence::from($this->recurrence);

        return [
            'title' => trim($this->title),
            'description' => filled($this->description) ? trim($this->description) : null,
            'category_id' => $this->category_id !== '' ? (int) $this->category_id : null,
            'priority' => $this->priority,
            'recurrence' => $recurrence,
            'recurrence_days' => match ($recurrence) {
                Recurrence::Weekly => collect($this->weekdays)->map(fn ($d) => (int) $d)->unique()->sort()->values()->all(),
                Recurrence::Monthly => [(int) $this->monthDay],
                default => null,
            },
            'scheduled_time' => $this->scheduled_time ?: null,
            'due_time' => $this->due_time ?: null,
            'default_assignee_id' => $this->default_assignee_id !== '' ? (int) $this->default_assignee_id : null,
            'starts_on' => $this->starts_on,
            'ends_on' => $this->ends_on ?: null,
        ];
    }
}
