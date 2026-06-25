@props(['class' => ''])

<tr {{ $attributes->merge(['class' => 'border-b border-border transition-colors hover:bg-muted/30 ' . $class]) }}>
    {{ $slot }}
</tr>
