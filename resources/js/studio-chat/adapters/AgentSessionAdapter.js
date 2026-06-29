import { fetchSse, jsonPostOptions } from '../utils/fetchSse';

export class AgentSessionAdapter {
    constructor({ streamUrl, uploadUrl }) {
        this.streamUrl = streamUrl;
        this.uploadUrl = uploadUrl;
    }

    async *send(message, attachments = [], context = {}) {
        const uploaded = await this.uploadAttachments(attachments);
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

    async uploadAttachments(attachments) {
        if (!attachments?.length || !this.uploadUrl) {
            return [];
        }

        const results = [];

        for (const attachment of attachments) {
            if (attachment.storageKey) {
                results.push({
                    type: attachment.type,
                    mime_type: attachment.mimeType,
                    storage_key: attachment.storageKey,
                    name: attachment.name,
                });
                continue;
            }

            if (!attachment.file) {
                continue;
            }

            const form = new FormData();
            form.append('file', attachment.file);
            form.append('type', attachment.type);

            const response = await fetch(this.uploadUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
                },
                body: form,
            });

            if (!response.ok) {
                throw new Error(`Upload failed for ${attachment.name}`);
            }

            const data = await response.json();
            results.push({
                type: attachment.type,
                mime_type: data.mime_type,
                storage_key: data.storage_key,
                name: data.name,
            });
        }

        return results;
    }
}
