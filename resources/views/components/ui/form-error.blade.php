@props(['class' => ''])

<p {{ $attributes->merge(['class' => 'text-xs text-destructive ' . $class]) }}>{{ $slot }}</p>
