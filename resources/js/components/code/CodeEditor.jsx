import { useMemo } from 'react';
import CodeMirror from '@uiw/react-codemirror';
import { cn } from '@/lib/utils';
import { buildCodeExtensions } from './extensions';

export default function CodeEditor({
    value = '',
    onChange,
    language = 'json',
    readOnly = false,
    lint = true,
    minHeight = '240px',
    height,
    className,
}) {
    const extensions = useMemo(
        () => buildCodeExtensions({ language, lint, readOnly }),
        [language, lint, readOnly],
    );

    return (
        <CodeMirror
            value={value}
            height={height ?? minHeight}
            extensions={extensions}
            onChange={readOnly ? undefined : onChange}
            readOnly={readOnly}
            theme="none"
            basicSetup={false}
            indentWithTab={false}
            className={cn('overflow-hidden rounded-md text-xs [&_.cm-editor]:bg-background', className)}
        />
    );
}
