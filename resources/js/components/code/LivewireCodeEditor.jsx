import { useState } from 'react';
import { Button } from '@/components/ui/button';
import CodeEditor from './CodeEditor';
import { tryFormatJson } from './format';

export default function LivewireCodeEditor({
    wireId,
    field,
    initialValue = '',
    language = 'json',
    minHeight = '384px',
    showFormat = true,
}) {
    const [value, setValue] = useState(initialValue);
    const [formatError, setFormatError] = useState('');

    const syncToLivewire = (nextValue) => {
        const component = window.Livewire?.find?.(wireId);

        if (component) {
            component.set(field, nextValue);
        }
    };

    const handleChange = (nextValue) => {
        setValue(nextValue);
        setFormatError('');
        syncToLivewire(nextValue);
    };

    const handleFormat = () => {
        const result = tryFormatJson(value);

        if (!result.ok) {
            setFormatError(result.error);
            return;
        }

        setFormatError('');
        setValue(result.value);
        syncToLivewire(result.value);
    };

    return (
        <div className="space-y-2">
            {showFormat && language === 'json' && (
                <div className="flex items-center gap-2">
                    <Button type="button" variant="outline" size="sm" onClick={handleFormat}>
                        Format JSON
                    </Button>
                </div>
            )}
            <CodeEditor
                value={value}
                onChange={handleChange}
                language={language}
                minHeight={minHeight}
            />
            {formatError && <p className="text-sm text-destructive">{formatError}</p>}
        </div>
    );
}
