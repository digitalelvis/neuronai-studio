@props(['class' => ''])

<div {{ $attributes->merge(['class' => 'flex flex-col space-y-1.5 p-4 ' . $class]) }}>
    {{ $slot }}
</div>
