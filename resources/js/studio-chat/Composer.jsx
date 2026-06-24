import { useRef, useState } from 'react';

const ACCEPT_MAP = {
    image: 'image/*',
    audio: 'audio/*',
    video: 'video/*',
    document: '*/*',
};

function detectType(file) {
    if (file.type.startsWith('image/')) return 'image';
    if (file.type.startsWith('audio/')) return 'audio';
    if (file.type.startsWith('video/')) return 'video';
    return 'document';
}

export default function Composer({ disabled, onSend, enableAttachments = false }) {
    const [text, setText] = useState('');
    const [attachments, setAttachments] = useState([]);
    const fileRef = useRef(null);

    const handleSubmit = (event) => {
        event.preventDefault();
        if (disabled || (!text.trim() && attachments.length === 0)) {
            return;
        }

        onSend(text.trim(), attachments);
        setText('');
        attachments.forEach((item) => {
            if (item.previewUrl) {
                URL.revokeObjectURL(item.previewUrl);
            }
        });
        setAttachments([]);
    };

    const handleKeyDown = (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            handleSubmit(event);
        }
    };

    const handleFiles = (files) => {
        const next = Array.from(files).map((file) => ({
            id: `${file.name}-${file.lastModified}`,
            type: detectType(file),
            name: file.name,
            mimeType: file.type,
            file,
            previewUrl: file.type.startsWith('image/') ? URL.createObjectURL(file) : null,
        }));

        setAttachments((current) => [...current, ...next]);
    };

    return (
        <form className="ab-chat-composer" onSubmit={handleSubmit}>
            {attachments.length > 0 && (
                <div className="ab-chat-composer-attachments">
                    {attachments.map((attachment) => (
                        <span key={attachment.id} className="ab-chat-composer-attachment">
                            {attachment.name}
                            <button
                                type="button"
                                className="ab-chat-attachment-remove"
                                onClick={() => {
                                    if (attachment.previewUrl) {
                                        URL.revokeObjectURL(attachment.previewUrl);
                                    }
                                    setAttachments((current) => current.filter((item) => item.id !== attachment.id));
                                }}
                            >
                                ×
                            </button>
                        </span>
                    ))}
                </div>
            )}
            <textarea
                className="ab-input ab-chat-input"
                rows={3}
                placeholder="Type a message…"
                value={text}
                disabled={disabled}
                onChange={(event) => setText(event.target.value)}
                onKeyDown={handleKeyDown}
            />
            <div className="ab-chat-composer-actions">
                {enableAttachments && (
                    <>
                        <input
                            ref={fileRef}
                            type="file"
                            multiple
                            hidden
                            accept={Object.values(ACCEPT_MAP).join(',')}
                            onChange={(event) => {
                                if (event.target.files?.length) {
                                    handleFiles(event.target.files);
                                }
                                event.target.value = '';
                            }}
                        />
                        <button
                            type="button"
                            className="ab-btn ab-btn-sm"
                            disabled={disabled}
                            onClick={() => fileRef.current?.click()}
                        >
                            Attach
                        </button>
                    </>
                )}
                <button type="submit" className="ab-btn ab-btn-primary ab-btn-sm" disabled={disabled}>
                    {disabled ? 'Sending…' : 'Send'}
                </button>
            </div>
        </form>
    );
}
