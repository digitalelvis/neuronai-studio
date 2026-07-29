<x-neuronai-studio::ui.button :href="route('neuronai-studio.tools.create')">{{ __('neuronai-studio::ui.actions.new_tool_class') }}</x-neuronai-studio::ui.button>
<x-neuronai-studio::ui.button variant="outline" :href="route('neuronai-studio.tools.create', ['kind' => 'webhook'])">{{ __('neuronai-studio::ui.actions.new_webhook') }}</x-neuronai-studio::ui.button>
<x-neuronai-studio::ui.button variant="outline" :href="route('neuronai-studio.tools.create', ['kind' => 'rag'])">{{ __('neuronai-studio::ui.actions.new_rag_tool') }}</x-neuronai-studio::ui.button>
