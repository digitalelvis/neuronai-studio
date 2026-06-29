@props(['class' => ''])

<tbody {{ $attributes->merge(['class' => '[&_tr:last-child]:border-0 ' . $class]) }}>
    {{ $slot }}
</tbody>
