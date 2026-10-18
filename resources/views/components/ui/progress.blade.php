@props(['value' => 0, 'label' => 'Progress'])

@php($value = max(0, min(100, (float) $value)))

<div {{ $attributes->class(['h-1.5 w-full overflow-hidden rounded-full bg-subtle']) }} role="progressbar" aria-label="{{ $label }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $value }}">
    <div class="h-full rounded-full bg-forest transition-[width] duration-300" style="width: {{ $value }}%"></div>
</div>
