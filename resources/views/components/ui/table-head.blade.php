@props(['class' => ''])

<thead {{ $attributes->merge(['class' => 'border-b border-border ' . $class]) }}>
    {{ $slot }}
</thead>
