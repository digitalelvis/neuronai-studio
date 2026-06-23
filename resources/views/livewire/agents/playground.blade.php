<div class="ab-grid ab-grid-2">
    <div class="ab-card">
        <h2>{{ $agent->name }}</h2>
        <p class="ab-muted">{{ $agent->provider }} / {{ $agent->model }}</p>

        @if (! empty($agent->tools))
            <p class="ab-muted ab-mt">
                <strong>Tools:</strong>
                {{ collect($agent->tools)->pluck('ref')->implode(', ') }}
            </p>
        @endif

        <form wire:submit="send" class="ab-mt">
            <div class="ab-form-group">
                <label>Message</label>
                <textarea wire:model="message" class="ab-input" rows="4" placeholder="Ask something..."></textarea>
            </div>
            <div class="ab-form-actions">
                <button type="button" wire:click="clear" class="ab-btn">Clear</button>
                <button type="submit" class="ab-btn ab-btn-primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="send">Send</span>
                    <span wire:loading wire:target="send">Thinking...</span>
                </button>
            </div>
        </form>
    </div>

    <div>
        <div class="ab-card">
            <h3>Response</h3>
            @if ($response)
                <div class="ab-response">{{ $response }}</div>
            @else
                <p class="ab-muted">Send a message to test this agent.</p>
            @endif
        </div>

        @if ($toolEvents)
            <div class="ab-card ab-mt">
                <h3>Tool Calls</h3>
                <div class="ab-tool-events">
                    @foreach ($toolEvents as $event)
                        <div class="ab-tool-event ab-tool-event-{{ $event['type'] }}">
                            <div class="ab-tool-event-header">
                                <strong>{{ $event['name'] }}</strong>
                                <span class="ab-badge">{{ $event['type'] }}</span>
                            </div>
                            @if (! empty($event['inputs']))
                                <div class="ab-muted">
                                    <strong>Inputs:</strong>
                                    <pre class="ab-code">{{ json_encode($event['inputs'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                </div>
                            @endif
                            @if ($event['result'] !== null)
                                <div class="ab-mt">
                                    <strong>Result:</strong>
                                    <pre class="ab-code">{{ $event['result'] }}</pre>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
