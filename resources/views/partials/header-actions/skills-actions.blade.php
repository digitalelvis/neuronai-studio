{{-- Header create shortcut kept for layout compatibility; primary actions live in the gallery. --}}
<x-neuronai-studio::ui.button :href="route('neuronai-studio.skills.create')" size="sm">
    {{ __('neuronai-studio::ui.actions.new_skill') }}
</x-neuronai-studio::ui.button>
