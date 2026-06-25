import { Badge } from '@/components/ui/badge';

export default function AgentMetaBar({ meta }) {
    if (!meta) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center gap-2 border-b border-border px-4 py-2">
            <span className="text-sm font-medium">{meta.name}</span>
            <Badge variant="secondary">{meta.provider}</Badge>
            <Badge variant="outline">{meta.model}</Badge>
            {meta.tools?.length > 0 && (
                <Badge variant="outline">{meta.tools.length} tool{meta.tools.length === 1 ? '' : 's'}</Badge>
            )}
            {meta.mcpServers?.length > 0 && (
                <Badge variant="outline">
                    {meta.mcpServers.length} MCP
                    {meta.mcpToolCount > 0 ? ` (${meta.mcpToolCount} tools)` : ''}
                </Badge>
            )}
        </div>
    );
}
