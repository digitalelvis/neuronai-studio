import { fetchSse, jsonPostOptions } from '../utils/fetchSse';

export class WorkflowSessionAdapter {
    constructor({ streamUrl, resumeUrlTemplate, onBeforeRun, syncCanvas = true }) {
        this.streamUrl = streamUrl;
        this.resumeUrlTemplate = resumeUrlTemplate;
        this.onBeforeRun = onBeforeRun;
        this.syncCanvas = syncCanvas;
        this.pendingResume = null;
    }

    async *send(message, attachments = [], context = {}) {
        if (this.pendingResume) {
            yield* this.resume(message);
            return;
        }

        if (this.onBeforeRun) {
            await this.onBeforeRun();
        }

        const state = context?.state && typeof context.state === 'object' ? context.state : {};

        yield* this.consumeStream(
            fetchSse(this.streamUrl, jsonPostOptions({
                message,
                state,
                attachments,
            })),
        );
    }

    async *resume(message) {
        if (!this.pendingResume) {
            throw new Error('No workflow run awaiting input.');
        }

        const { runId, nodeId } = this.pendingResume;
        this.pendingResume = null;

        const url = this.resumeUrlTemplate.replace('__RUN__', String(runId));

        yield* this.consumeStream(
            fetchSse(url, jsonPostOptions({
                message,
                node_id: nodeId,
            })),
        );
    }

    async *consumeStream(stream) {
        for await (const packet of stream) {
            if (this.syncCanvas && ['step_started', 'step_completed', 'run_completed', 'run_failed'].includes(packet.event)) {
                window.dispatchEvent(
                    new CustomEvent('canvas-execution-event', {
                        detail: { event: packet.event, ...(typeof packet.data === 'object' ? packet.data : {}) },
                    }),
                );
            }

            if (packet.event === 'human_input_required') {
                this.pendingResume = {
                    runId: packet.data.run_id,
                    nodeId: packet.data.node_id,
                };
            }

            yield packet;
        }
    }

    reset() {
        this.pendingResume = null;
    }
}
