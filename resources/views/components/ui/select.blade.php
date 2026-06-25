@props(['class' => ''])

<select {{ $attributes->merge(['class' => 'flex h-9 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 ' . $class]) }}>
    {{ $slot }}
</select>
