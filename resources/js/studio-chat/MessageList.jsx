function AttachmentPreview({ attachment }) {
    if (attachment.type === 'image' && attachment.previewUrl) {
        return (
            <img src={attachment.previewUrl} alt={attachment.name} className="ab-chat-attachment-thumb" />
        );
    }

    return (
        <span className="ab-chat-attachment-file">
            {attachment.type}: {attachment.name}
        </span>
    );
}

export default function MessageList({ messages }) {
    if (!messages.length) {
        return (
            <div className="ab-chat-empty">
                <p>Send a message to start testing.</p>
            </div>
        );
    }

    return (
        <div className="ab-chat-messages">
            {messages.map((message) => (
                <div key={message.id} className={`ab-chat-bubble ab-chat-bubble--${message.role}`}>
                    <div className="ab-chat-bubble-role">{message.role}</div>
                    {message.meta?.status === 'awaiting_input' && (
                        <span className="ab-chat-badge">Awaiting your input</span>
                    )}
                    <div className="ab-chat-bubble-content">
                        {message.content}
                        {message.streaming && <span className="ab-chat-cursor">▍</span>}
                    </div>
                    {message.attachments?.length > 0 && (
                        <div className="ab-chat-attachments">
                            {message.attachments.map((attachment) => (
                                <AttachmentPreview key={attachment.id} attachment={attachment} />
                            ))}
                        </div>
                    )}
                    {message.meta?.toolEvents?.map((tool, index) => (
                        <div key={`${message.id}-tool-${index}`} className="ab-chat-tool-event">
                            <strong>{tool.name}</strong>
                            <span className="ab-chat-tool-type">{tool.type}</span>
                            {tool.inputs && (
                                <pre className="ab-code ab-code-sm">{JSON.stringify(tool.inputs, null, 2)}</pre>
                            )}
                            {tool.result != null && (
                                <pre className="ab-code ab-code-sm">{String(tool.result)}</pre>
                            )}
                        </div>
                    ))}
                </div>
            ))}
        </div>
    );
}
