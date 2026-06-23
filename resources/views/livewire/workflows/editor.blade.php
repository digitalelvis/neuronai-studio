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
                <button type="button" wire:click="runWorkflow" class="ab-btn">Run Test</button>
                <button type="button" wire:click="exportWorkflow" class="ab-btn">Export PHP</button>
                <button type="button" @click="syncAndSave()" class="ab-btn ab-btn-primary">Save</button>
            </div>
            @if ($validationMessage)
                <p class="ab-mt ab-muted">{{ $validationMessage }}</p>
            @endif
        </div>
    </form>

    <div class="ab-card ab-mt ab-editor-layout" wire:ignore
         x-data="workflowCanvas(@js($graph), @js($nodeTypes), @js($agentsForCanvas))"
         x-init="init()">
        <aside class="ab-palette">
            <h3>Nodes</h3>
            <template x-for="(meta, type) in nodeTypes" :key="type">
                <button type="button" class="ab-palette-item" @click="addNode(type)" x-text="meta.label || type"></button>
            </template>
        </aside>
        <div class="ab-canvas-wrap">
            <svg class="ab-canvas-edges" x-ref="edges"></svg>
            <div class="ab-canvas-nodes" x-ref="nodes"></div>
        </div>
        <aside class="ab-inspector" x-show="selectedNode">
            <h3>Node Config</h3>
            <template x-if="selectedNode">
                <div>
                    <p><strong x-text="selectedNode.type"></strong></p>
                    <template x-if="selectedNode.type === 'agent'">
                        <div class="ab-form-group">
                            <label>Agent</label>
                            <select class="ab-input" x-model.number="selectedNode.data.agent_id">
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
                            <textarea class="ab-input" rows="3" x-model="selectedNode.data.prompt"></textarea>
                        </div>
                    </template>
                    <template x-if="selectedNode.type === 'set_state'">
                        <div class="ab-form-group">
                            <label>Key</label>
                            <input class="ab-input" x-model="selectedNode.data.key">
                            <label class="ab-mt">Value</label>
                            <input class="ab-input" x-model="selectedNode.data.value">
                        </div>
                    </template>
                    <template x-if="selectedNode.type === 'condition'">
                        <div class="ab-form-group">
                            <label>State Key</label>
                            <input class="ab-input" x-model="selectedNode.data.state_key">
                        </div>
                    </template>
                    <button type="button" class="ab-btn ab-danger ab-mt" @click="removeSelected()">Remove Node</button>
                </div>
            </template>
        </aside>
    </div>
</div>
