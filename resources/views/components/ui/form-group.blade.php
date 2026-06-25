@props(['class' => ''])

<div {{ $attributes->merge(['class' => 'space-y-2 ' . $class]) }}>
    {{ $slot }}
</div>
