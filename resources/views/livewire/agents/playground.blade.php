<div class="ab-grid ab-grid-2">
    <div class="ab-card">
        <h2>{{ $agent->name }}</h2>
        <p class="ab-muted">{{ $agent->provider }} / {{ $agent->model }}</p>

        <form wire:submit="send" class="ab-mt">
            <div class="ab-form-group">
                <label>Message</label>
                <textarea wire:model="message" class="ab-input" rows="4" placeholder="Ask something..."></textarea>
            </div>
            <button type="submit" class="ab-btn ab-btn-primary" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="send">Send</span>
                <span wire:loading wire:target="send">Thinking...</span>
            </button>
        </form>
    </div>

    <div class="ab-card">
        <h3>Response</h3>
        @if ($response)
            <div class="ab-response">{{ $response }}</div>
        @else
            <p class="ab-muted">Send a message to test this agent.</p>
        @endif
    </div>
</div>
