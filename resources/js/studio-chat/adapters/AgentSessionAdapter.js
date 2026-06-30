import { fetchSse, jsonPostOptions } from '../utils/fetchSse';
import { uploadAttachments } from '../utils/uploadAttachments';

export class AgentSessionAdapter {
    constructor({ streamUrl, uploadUrl }) {
        this.streamUrl = streamUrl;
        this.uploadUrl = uploadUrl;
    }

    async *send(message, attachments = [], context = {}) {
        const uploaded = await uploadAttachments(attachments, this.uploadUrl);
        const state = context?.state && typeof context.state === 'object' ? context.state : {};
        const payload = {
            message,
            context: state,
            attachments: uploaded,
        };

        if (context.instructions) {
            payload.instructions = context.instructions;
        }

        const parameters = context.parameters ?? {};
        const normalizedParameters = Object.fromEntries(
            Object.entries(parameters).filter(([, value]) => value !== null && value !== undefined && value !== ''),
        );

        if (Object.keys(normalizedParameters).length > 0) {
            payload.parameters = normalizedParameters;
        }

        if (context.threadId) {
            payload.thread_id = context.threadId;
        }

        yield* fetchSse(this.streamUrl, jsonPostOptions(payload));
    }

    reset() {}
}
