@props(['class' => ''])

<th {{ $attributes->merge(['class' => 'h-10 px-4 text-left align-middle text-xs font-medium uppercase tracking-wide text-muted-foreground ' . $class]) }}>
    {{ $slot }}
</th>
