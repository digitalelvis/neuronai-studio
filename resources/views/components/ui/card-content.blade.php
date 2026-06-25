@props(['class' => ''])

<div {{ $attributes->merge(['class' => 'p-4 pt-0 ' . $class]) }}>
    {{ $slot }}
</div>
