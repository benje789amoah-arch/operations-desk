{{-- Lifecycle chip for an activity definition (see ActivityDefinition::lifecycle()). --}}
@props(['definition'])

@switch($definition->lifecycle())
    @case('archived')
        <span {{ $attributes->class(['chip chip-neutral']) }}><x-lucide-archive class="size-3" aria-hidden="true" /> Archived {{ ops_format($definition->archived_at, 'd M Y') }}</span>
        @break
    @case('ended')
        <span {{ $attributes->class(['chip chip-neutral']) }}><x-lucide-calendar-x class="size-3" aria-hidden="true" /> Ended {{ $definition->ends_on->format('d M Y') }}</span>
        @break
    @case('upcoming')
        <span {{ $attributes->class(['chip chip-pending']) }}><x-lucide-calendar-clock class="size-3" aria-hidden="true" /> Starts {{ $definition->starts_on->format('d M Y') }}</span>
        @break
    @default
        <span {{ $attributes->class(['chip chip-done']) }}><x-lucide-circle-check class="size-3" aria-hidden="true" /> Active</span>
@endswitch
