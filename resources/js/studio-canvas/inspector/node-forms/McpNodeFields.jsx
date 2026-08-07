import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import OutputKeyField from './fields/OutputKeyField';
import ParametersJsonField from './fields/ParametersJsonField';

export default function McpNodeFields({
    data,
    mcpServers = [],
    readOnly = false,
    showControls = true,
    showAdvanced = true,
    onUpdate,
}) {
    const updateField = (key, value) => {
        onUpdate?.({ ...data, [key]: value });
    };

    return (
        <>
            {showControls && (
                <>
                    <div className="space-y-2">
                        <Label>MCP Server</Label>
                        <Select
                            value={data.mcp_server ?? ''}
                            onValueChange={(value) => updateField('mcp_server', value)}
                            disabled={readOnly}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select server" />
                            </SelectTrigger>
                            <SelectContent>
                                {mcpServers.map((server) => (
                                    <SelectItem key={server.slug} value={server.slug}>
                                        {server.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-2">
                        <Label>Tool Name</Label>
                        <Input
                            value={data.tool_name ?? ''}
                            onChange={(e) => updateField('tool_name', e.target.value)}
                            disabled={readOnly}
                        />
                    </div>
                    <OutputKeyField
                        value={data.output_key}
                        defaultValue="mcp_result"
                        onChange={(value) => updateField('output_key', value)}
                        readOnly={readOnly}
                    />
                </>
            )}
            {showAdvanced && (
                <ParametersJsonField data={data} readOnly={readOnly} onUpdate={onUpdate} />
            )}
        </>
    );
}
