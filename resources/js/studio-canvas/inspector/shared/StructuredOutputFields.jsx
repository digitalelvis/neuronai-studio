import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export default function StructuredOutputFields({
    structured = false,
    outputClass = '',
    outputClasses = [],
    readOnly = false,
    onChange,
}) {
    const selected = outputClasses.find((item) => item.class === outputClass) ?? null;

    const handleStructuredChange = (checked) => {
        if (checked) {
            onChange?.({ structured: true });
            return;
        }

        onChange?.({ structured: false, output_class: '' });
    };

    const handleOutputClassChange = (value) => {
        onChange?.({ structured: true, output_class: value });
    };

    return (
        <div className="space-y-3 rounded-md border border-border bg-muted/20 p-3">
            <div className="flex items-center justify-between gap-3">
                <div className="space-y-0.5">
                    <Label htmlFor="structured-output-toggle">Structured output</Label>
                    <p className="text-xs text-muted-foreground">
                        Validate and store typed output instead of plain text.
                    </p>
                </div>
                <Checkbox
                    id="structured-output-toggle"
                    checked={Boolean(structured)}
                    onCheckedChange={handleStructuredChange}
                    disabled={readOnly}
                />
            </div>

            {structured && (
                <>
                    <div className="space-y-2">
                        <Label>Output class</Label>
                        <Select
                            value={outputClass || ''}
                            onValueChange={handleOutputClassChange}
                            disabled={readOnly || outputClasses.length === 0}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder={outputClasses.length === 0 ? 'No classes found' : 'Select output class'} />
                            </SelectTrigger>
                            <SelectContent>
                                {outputClasses.map((item) => (
                                    <SelectItem key={item.class} value={item.class}>
                                        {item.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {outputClasses.length === 0 && (
                            <p className="text-xs text-muted-foreground">
                                Add output classes under configured scan paths.
                            </p>
                        )}
                    </div>

                    {selected?.properties?.length > 0 && (
                        <div className="space-y-1">
                            <Label className="text-xs text-muted-foreground">Schema preview</Label>
                            <ul className="space-y-1 rounded-md border border-border bg-background px-2 py-1.5 text-xs">
                                {selected.properties.map((property) => (
                                    <li key={property.name} className="font-mono">
                                        <span className="text-foreground">{property.name}</span>
                                        {property.type && (
                                            <span className="text-muted-foreground">: {property.type}</span>
                                        )}
                                        {property.required && (
                                            <span className="ml-1 text-muted-foreground">(required)</span>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </>
            )}
        </div>
    );
}
