import { useEffect, useRef, useState } from 'react';
import { Braces, Paperclip, Send } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

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

export default function Composer({
    disabled,
    onSend,
    enableAttachments = false,
    enableInputJson = false,
    inputJson = '{}',
    onInputJsonChange,
    inputJsonError = '',
}) {
    const [text, setText] = useState('');
    const [attachments, setAttachments] = useState([]);
    const [inputOpen, setInputOpen] = useState(false);
    const fileRef = useRef(null);
    const previewUrlsRef = useRef([]);

    useEffect(() => {
        return () => {
            previewUrlsRef.current.forEach((url) => URL.revokeObjectURL(url));
            previewUrlsRef.current = [];
        };
    }, []);

    const hasCustomInput = enableInputJson && inputJson.trim() !== '{}' && inputJson.trim() !== '';
    const sendDisabled = disabled || (enableInputJson && Boolean(inputJsonError));

    const handleSubmit = (event) => {
        event.preventDefault();
        if (sendDisabled || (!text.trim() && attachments.length === 0)) {
            return;
        }

        onSend(text.trim(), attachments);
        setText('');
        setAttachments([]);
    };

    const handleKeyDown = (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            handleSubmit(event);
        }
    };

    const handleFiles = (files) => {
        const next = Array.from(files).map((file) => {
            const previewUrl = file.type.startsWith('image/') ? URL.createObjectURL(file) : null;
            if (previewUrl) {
                previewUrlsRef.current.push(previewUrl);
            }

            return {
                id: `${file.name}-${file.lastModified}`,
                type: detectType(file),
                name: file.name,
                mimeType: file.type,
                file,
                previewUrl,
            };
        });

        setAttachments((current) => [...current, ...next]);
    };

    return (
        <form className="space-y-2" onSubmit={handleSubmit}>
            {enableInputJson && inputOpen && (
                <div className="space-y-1">
                    <Textarea
                        rows={4}
                        placeholder='{"key": "value"}'
                        value={inputJson}
                        disabled={disabled}
                        onChange={(event) => onInputJsonChange?.(event.target.value)}
                        className="resize-none font-mono text-xs"
                    />
                    {inputJsonError && <p className="text-xs text-destructive">{inputJsonError}</p>}
                </div>
            )}
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
                                        previewUrlsRef.current = previewUrlsRef.current.filter(
                                            (url) => url !== attachment.previewUrl,
                                        );
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
                {enableInputJson && (
                    <Button
                        type="button"
                        variant={inputOpen ? 'secondary' : 'outline'}
                        size="sm"
                        disabled={disabled}
                        onClick={() => setInputOpen((open) => !open)}
                        className={cn(hasCustomInput && !inputOpen && 'border-primary/50')}
                    >
                        <Braces className="h-4 w-4" />
                        Input
                        {hasCustomInput && (
                            <span className="ml-1 h-1.5 w-1.5 rounded-full bg-primary" />
                        )}
                    </Button>
                )}
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
                <Button type="submit" size="sm" disabled={sendDisabled}>
                    <Send className="h-4 w-4" />
                    {disabled ? 'Sending…' : 'Send'}
                </Button>
            </div>
        </form>
    );
}
