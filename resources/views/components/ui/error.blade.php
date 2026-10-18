@props(['for', 'bag' => 'default'])

@error($for, $bag)
    <p {{ $attributes->class(['field-error']) }} id="{{ str_replace('.', '-', $for) }}-error" role="alert">
        <x-lucide-circle-alert class="mt-px size-3.5 shrink-0" aria-hidden="true" />
        <span>{{ $message }}</span>
    </p>
@enderror
