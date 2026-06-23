<form wire:submit="save" class="ab-form ab-card">
    <div class="ab-form-group">
        <label>Name</label>
        <input type="text" wire:model="name" class="ab-input" required>
        @error('name') <span class="ab-error">{{ $message }}</span> @enderror
    </div>

    <div class="ab-form-group">
        <label>Description</label>
        <textarea wire:model="description" class="ab-input" rows="2"></textarea>
    </div>

    <div class="ab-form-row">
        <div class="ab-form-group">
            <label>Provider</label>
            <select wire:model.live="provider" class="ab-input">
                @foreach ($providers as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="ab-form-group">
            <label>Model</label>
            <select wire:model="model" class="ab-input">
                @foreach ($models as $modelOption)
                    <option value="{{ $modelOption }}">{{ $modelOption }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="ab-form-group">
        <label>Instructions (System Prompt)</label>
        <textarea wire:model="instructions" class="ab-input" rows="8" placeholder="You are a helpful assistant..."></textarea>
    </div>

    <div class="ab-form-actions">
        <a href="{{ route('neuronai-studio.agents.index') }}" class="ab-btn">Cancel</a>
        <button type="submit" class="ab-btn ab-btn-primary">Save Agent</button>
    </div>
</form>
