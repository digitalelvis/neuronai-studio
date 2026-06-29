@props(['class' => ''])

<div {{ $attributes->merge(['class' => 'overflow-x-auto ' . $class]) }}>
    <table class="w-full caption-bottom text-sm">
        {{ $slot }}
    </table>
</div>
