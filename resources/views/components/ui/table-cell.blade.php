@props(['class' => ''])

<td {{ $attributes->merge(['class' => 'px-4 py-3 align-middle ' . $class]) }}>
    {{ $slot }}
</td>
