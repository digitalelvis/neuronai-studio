@props(['class' => ''])

<pre {{ $attributes->merge(['class' => 'overflow-x-auto rounded-md border border-border bg-background p-4 font-mono text-xs leading-relaxed ' . $class]) }}>{{ $slot }}</pre>
