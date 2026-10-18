@props(['priority', 'quiet' => false])

@php($priority = $priority instanceof \App\Enums\Priority ? $priority : \App\Enums\Priority::from($priority))

@if ($priority === \App\Enums\Priority::Critical)
    <span {{ $attributes->class(['chip chip-critical']) }}><x-lucide-triangle-alert class="size-3" aria-hidden="true" /> Critical</span>
@elseif ($priority === \App\Enums\Priority::High)
    <span {{ $attributes->class(['chip chip-pending']) }}><x-lucide-arrow-up class="size-3" aria-hidden="true" /> High</span>
@elseif (! $quiet)
    <span {{ $attributes->class(['chip chip-neutral']) }}>Normal</span>
@endif
