@props(['class' => ''])

<dl {{ $attributes->merge(['class' => 'space-y-3 ' . $class]) }}>
    {{ $slot }}
</dl>
