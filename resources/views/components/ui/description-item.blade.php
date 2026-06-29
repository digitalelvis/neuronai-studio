@props(['term'])

<div class="space-y-1">
    <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ $term }}</dt>
    <dd class="text-sm">{{ $slot }}</dd>
</div>
