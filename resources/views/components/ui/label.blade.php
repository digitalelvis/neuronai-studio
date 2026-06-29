@props(['class' => ''])

<label {{ $attributes->merge(['class' => 'text-sm font-medium leading-none text-foreground ' . $class]) }}>
    {{ $slot }}
</label>
