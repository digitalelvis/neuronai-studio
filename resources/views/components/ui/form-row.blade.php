@props(['class' => ''])

<div {{ $attributes->merge(['class' => 'grid gap-4 md:grid-cols-2 ' . $class]) }}>
    {{ $slot }}
</div>
