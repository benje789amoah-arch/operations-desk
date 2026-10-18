{{--
    Activity state chip. "not_updated" is deliberately distinct from
    "pending": the first means nobody has reported on it yet, the second
    means someone reported it is still outstanding.
--}}
@props(['state'])

@php
    $state = $state instanceof \App\Enums\ActivityStatus ? $state->value : $state;
    [$class, $icon, $label] = match ($state) {
        'done' => ['chip-done', 'check', 'Done'],
        'pending' => ['chip-pending', 'circle-dot', 'Pending'],
        'reopened' => ['chip-pending', 'rotate-ccw', 'Reopened'],
        default => ['chip-neutral', 'circle-dashed', 'Not updated'],
    };
@endphp

<span {{ $attributes->class(['chip', $class]) }}>
    <x-dynamic-component :component="'lucide-'.$icon" class="size-3" aria-hidden="true" />
    {{ $label }}
</span>
