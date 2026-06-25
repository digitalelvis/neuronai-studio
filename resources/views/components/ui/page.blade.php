@props(['class' => ''])

<div {{ $attributes->merge(['class' => 'studio-page ' . $class]) }}>
    {{ $slot }}
</div>
