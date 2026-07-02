import { fetchSse, jsonPostOptions } from '../utils/fetchSse';
import { uploadAttachments } from '../utils/uploadAttachments';

export class WorkflowSessionAdapter {
    constructor({ streamUrl, resumeUrlTemplate, uploadUrl, onBeforeRun, syncCanvas = true }) {
        this.streamUrl = streamUrl;
        this.resumeUrlTemplate = resumeUrlTemplate;
        this.uploadUrl = uploadUrl;
        this.onBeforeRun = onBeforeRun;
        this.syncCanvas = syncCanvas;
        this.pendingResume = null;
    }

    async *send(message, attachments = [], context = {}) {
        if (this.pendingResume) {
            yield* this.resume(message, attachments);
            return;
        }

        if (this.onBeforeRun) {
            await this.onBeforeRun();
        }

        const uploaded = await uploadAttachments(attachments, this.uploadUrl);
        const state = context?.state && typeof context.state === 'object' ? context.state : {};
        const payload = {
            message,
            state,
            attachments: uploaded,
        };

        if (context?.threadId) {
            payload.thread_id = context.threadId;
        }

        yield* this.consumeStream(
            fetchSse(this.streamUrl, jsonPostOptions(payload)),
        );
    }

    async *resume(message, attachments = []) {
        if (!this.pendingResume) {
            throw new Error('No workflow trace awaiting input.');
        }

        const { traceId, nodeId } = this.pendingResume;
        this.pendingResume = null;

        const uploaded = await uploadAttachments(attachments, this.uploadUrl);
        const url = this.resumeUrlTemplate.replace('__TRACE__', String(traceId));

        const payload = {
            message,
            node_id: nodeId,
        };

        if (uploaded.length > 0) {
            payload.attachments = uploaded;
        }

        yield* this.consumeStream(
            fetchSse(url, jsonPostOptions(payload)),
        );
    }

    async *consumeStream(stream) {
        for await (const packet of stream) {
            const canvasEvents = [
                'step_started',
                'step_completed',
                'loop_iteration',
                'trace_completed',
                'trace_failed',
            ];

            if (this.syncCanvas && canvasEvents.includes(packet.event)) {
                window.dispatchEvent(
                    new CustomEvent('canvas-execution-event', {
                        detail: { event: packet.event, ...(typeof packet.data === 'object' ? packet.data : {}) },
                    }),
                );
            }

            if (packet.event === 'human_input_required') {
                this.pendingResume = {
                    traceId: packet.data.trace_id,
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
