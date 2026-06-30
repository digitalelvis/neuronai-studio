function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

/**
 * @param {Array<{ file?: File, type: string, name?: string, mimeType?: string, storageKey?: string }>} attachments
 * @param {string|null|undefined} uploadUrl
 * @returns {Promise<Array<{ type: string, mime_type: string, storage_key: string, name: string, url?: string }>>}
 */
export async function uploadAttachments(attachments, uploadUrl) {
    if (!attachments?.length || !uploadUrl) {
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
                url: attachment.url,
            });
            continue;
        }

        if (!attachment.file) {
            continue;
        }

        const form = new FormData();
        form.append('file', attachment.file);
        form.append('type', attachment.type);

        const response = await fetch(uploadUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
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
                url: data.url,
            });
    }

    return results;
}
