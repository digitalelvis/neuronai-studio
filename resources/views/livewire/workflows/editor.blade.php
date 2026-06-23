<div>
    <form wire:submit="save" class="ab-form">
        <div class="ab-card">
            <div class="ab-form-row">
                <div class="ab-form-group">
                    <label>Name</label>
                    <input type="text" wire:model="name" class="ab-input" required>
                </div>
                <div class="ab-form-group">
                    <label>Status</label>
                    <select wire:model="status" class="ab-input">
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                    </select>
                </div>
            </div>
            <div class="ab-form-group">
                <label>Description</label>
                <textarea wire:model="description" class="ab-input" rows="2"></textarea>
            </div>
            <div class="ab-form-actions">
                <button type="button" wire:click="validateGraph" class="ab-btn">Validate</button>
                <button
                    type="button"
                    class="ab-btn"
                    data-workflow-run-test
                    @if (! $workflow?->exists) disabled title="Save the workflow first" @endif
                >Run Test</button>
                <button type="button" wire:click="exportWorkflow" class="ab-btn">Export PHP</button>
                <button type="button" onclick="window.dispatchEvent(new CustomEvent('workflow-canvas-save'))" class="ab-btn ab-btn-primary">Save</button>
            </div>
            @if ($validationMessage)
                <p class="ab-mt ab-muted">{{ $validationMessage }}</p>
            @endif
        </div>
    </form>

    <script>
        window.__NEURONAI_CANVAS_CONFIG = {
            graph: @json($graph),
            nodeTypes: @json($nodeTypes),
            wireId: @json($this->getId()),
            workflowId: @json($workflow?->id),
            streamUrl: @json($workflow?->exists ? route('neuronai-studio.workflows.run.stream', $workflow) : null),
            tools: @json($toolsForCanvas),
        };
    </script>

    <div class="ab-card ab-mt ab-editor-layout" wire:ignore>
        <aside class="ab-palette">
            <h3>Nodes</h3>
            <p class="ab-palette-hint">Drag to canvas</p>
            @foreach ($nodeTypes as $type => $meta)
                <div
                    class="ab-palette-item"
                    draggable="true"
                    data-canvas-node-type="{{ $type }}"
                    role="button"
                    tabindex="0"
                >{{ $meta['label'] ?? $type }}</div>
            @endforeach
        </aside>
        <div class="ab-canvas-wrap">
            <div id="workflow-canvas-root" class="ab-canvas-root"></div>
        </div>
        <aside
            class="ab-inspector"
            x-data="workflowInspector(@js($agentsForCanvas), @js($toolsForCanvas))"
            x-init="init()"
            x-show="selectedNode"
            x-cloak
        >
            <h3>Node Config</h3>
            <template x-if="selectedNode">
                <div>
                    <p><strong x-text="selectedNode.type"></strong></p>
                    <template x-if="selectedNode.type === 'agent'">
                        <div class="ab-form-group">
                            <label>Agent</label>
                            <select class="ab-input" x-model.number="selectedNode.data.agent_id" @change="syncNode()">
                                <option value="">Select agent</option>
                                <template x-for="agent in agents" :key="agent.id">
                                    <option :value="agent.id" x-text="agent.name"></option>
                                </template>
                            </select>
                        </div>
                    </template>
                    <template x-if="selectedNode.type === 'llm'">
                        <div class="ab-form-group">
                            <label>Prompt</label>
                            <textarea class="ab-input" rows="3" x-model="selectedNode.data.prompt" @change="syncNode()"></textarea>
                        </div>
                    </template>
                    <template x-if="selectedNode.type === 'set_state'">
                        <div class="ab-form-group">
                            <label>Key</label>
                            <input class="ab-input" x-model="selectedNode.data.key" @change="syncNode()">
                            <label class="ab-mt">Value</label>
                            <input class="ab-input" x-model="selectedNode.data.value" @change="syncNode()">
                        </div>
                    </template>
                    <template x-if="selectedNode.type === 'condition'">
                        <div class="ab-form-group">
                            <label>State Key</label>
                            <input class="ab-input" x-model="selectedNode.data.state_key" @change="syncNode()">
                        </div>
                    </template>
                    <template x-if="selectedNode.type === 'tool'">
                        <div class="ab-form-group">
                            <label>Tool</label>
                            <select class="ab-input" x-model="selectedNode.data.tool_ref" @change="syncNode()">
                                <option value="">Select tool</option>
                                <template x-for="tool in tools" :key="tool.ref">
                                    <option :value="tool.ref" x-text="tool.label"></option>
                                </template>
                            </select>
                            <label class="ab-mt">Output Key</label>
                            <input class="ab-input" x-model="selectedNode.data.output_key" @change="syncNode()" placeholder="tool_result">
                            <label class="ab-mt">Parameters JSON</label>
                            <textarea class="ab-input" rows="3" x-model="selectedNode.data.parameters_json" @change="syncParameters()" placeholder='{"query": "$input"}'></textarea>
                        </div>
                    </template>
                    <button type="button" class="ab-btn ab-danger ab-mt" @click="removeSelected()">Remove Node</button>
                </div>
            </template>
        </aside>
    </div>
</div>
