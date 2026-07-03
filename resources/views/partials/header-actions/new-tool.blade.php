<x-neuronai-studio::ui.button :href="route('neuronai-studio.tools.create')">New Tool Class</x-neuronai-studio::ui.button>
<x-neuronai-studio::ui.button variant="outline" :href="route('neuronai-studio.tools.create', ['kind' => 'webhook'])">New Webhook</x-neuronai-studio::ui.button>
<x-neuronai-studio::ui.button variant="outline" :href="route('neuronai-studio.tools.create', ['kind' => 'rag'])">New RAG Tool</x-neuronai-studio::ui.button>
