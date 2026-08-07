import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export default function ConditionOperatorFields({
    operator,
    value,
    onOperatorChange,
    onValueChange,
    readOnly = false,
    operatorLabel = 'Operator',
    valueLabel = 'Value',
}) {
    return (
        <>
            <div className="space-y-2">
                <Label>{operatorLabel}</Label>
                <Select
                    value={operator ?? 'not_empty'}
                    onValueChange={onOperatorChange}
                    disabled={readOnly}
                >
                    <SelectTrigger>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="not_empty">is not empty</SelectItem>
                        <SelectItem value="empty">is empty</SelectItem>
                        <SelectItem value="equals">equals</SelectItem>
                        <SelectItem value="not_equals">does not equal</SelectItem>
                        <SelectItem value="contains">contains</SelectItem>
                    </SelectContent>
                </Select>
            </div>
            {['equals', 'not_equals', 'contains'].includes(operator) && (
                <div className="space-y-2">
                    <Label>{valueLabel}</Label>
                    <Input
                        value={value ?? ''}
                        onChange={(e) => onValueChange?.(e.target.value)}
                        disabled={readOnly}
                    />
                </div>
            )}
        </>
    );
}
