import { useRef, useState } from 'react';
import { Paperclip, Send } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';

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
        <form className="space-y-2" onSubmit={handleSubmit}>
            {attachments.length > 0 && (
                <div className="flex flex-wrap gap-2">
                    {attachments.map((attachment) => (
                        <span
                            key={attachment.id}
                            className="inline-flex items-center gap-1 rounded-md border border-border bg-muted px-2 py-1 text-xs"
                        >
                            {attachment.name}
                            <button
                                type="button"
                                className="text-muted-foreground hover:text-foreground"
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
            <Textarea
                rows={3}
                placeholder="Type a message…"
                value={text}
                disabled={disabled}
                onChange={(event) => setText(event.target.value)}
                onKeyDown={handleKeyDown}
                className="resize-none"
            />
            <div className="flex items-center justify-end gap-2">
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
                        <Button type="button" variant="outline" size="sm" disabled={disabled} onClick={() => fileRef.current?.click()}>
                            <Paperclip className="h-4 w-4" />
                            Attach
                        </Button>
                    </>
                )}
                <Button type="submit" size="sm" disabled={disabled}>
                    <Send className="h-4 w-4" />
                    {disabled ? 'Sending…' : 'Send'}
                </Button>
            </div>
        </form>
    );
}
