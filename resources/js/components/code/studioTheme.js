import { EditorView } from '@codemirror/view';
import { HighlightStyle, syntaxHighlighting } from '@codemirror/language';
import { tags as t } from '@lezer/highlight';

export const studioSyntaxHighlight = syntaxHighlighting(
    HighlightStyle.define([
        { tag: t.keyword, color: 'hsl(239 84% 74%)' },
        { tag: [t.string, t.special(t.string)], color: 'hsl(142 71% 55%)' },
        { tag: [t.number, t.bool, t.null], color: 'hsl(32 95% 58%)' },
        { tag: [t.propertyName, t.definition(t.propertyName)], color: 'hsl(199 89% 62%)' },
        { tag: [t.className, t.typeName], color: 'hsl(280 65% 72%)' },
        { tag: [t.comment, t.lineComment, t.blockComment], color: 'hsl(215 20.2% 55%)' },
        { tag: [t.variableName, t.name], color: 'hsl(210 40% 92%)' },
        { tag: [t.operator, t.punctuation, t.bracket], color: 'hsl(215 20.2% 72%)' },
        { tag: t.meta, color: 'hsl(215 20.2% 65%)' },
    ]),
);

export const studioTheme = EditorView.theme(
    {
        '&': {
            backgroundColor: 'hsl(var(--background))',
            color: 'hsl(var(--foreground))',
            fontSize: '12px',
        },
        '.cm-content': {
            fontFamily: 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace',
            caretColor: 'hsl(var(--foreground))',
            color: 'hsl(var(--foreground))',
            backgroundColor: 'hsl(var(--background))',
        },
        '.cm-scroller': {
            backgroundColor: 'hsl(var(--background))',
        },
        '.cm-line': {
            color: 'hsl(var(--foreground))',
        },
        '.cm-gutters': {
            backgroundColor: 'hsl(var(--muted) / 0.35)',
            color: 'hsl(var(--muted-foreground))',
            borderRight: '1px solid hsl(var(--border))',
        },
        '.cm-activeLineGutter': {
            backgroundColor: 'hsl(var(--muted) / 0.5)',
            color: 'hsl(var(--foreground))',
        },
        '.cm-activeLine': {
            backgroundColor: 'hsl(var(--muted) / 0.2)',
        },
        '.cm-selectionBackground, &.cm-focused .cm-selectionBackground': {
            backgroundColor: 'hsl(var(--primary) / 0.25) !important',
        },
        '.cm-cursor': {
            borderLeftColor: 'hsl(var(--foreground))',
        },
        '.cm-lintRange-error': {
            backgroundImage: 'url("data:image/svg+xml,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'6\' height=\'3\'><path d=\'m0 3 l2 -2 l1 0 l2 2 l1 0\' fill=\'%23ef4444\'/></svg>")',
        },
        '.cm-tooltip.cm-tooltip-lint': {
            backgroundColor: 'hsl(var(--popover))',
            color: 'hsl(var(--popover-foreground))',
            border: '1px solid hsl(var(--border))',
            borderRadius: 'calc(var(--radius) - 2px)',
        },
        '.cm-diagnostic-error': {
            color: 'hsl(var(--destructive))',
        },
    },
    { dark: true },
);

export const studioEditorStyles = EditorView.baseTheme({
    '&.cm-editor': {
        borderRadius: 'calc(var(--radius) - 2px)',
        border: '1px solid hsl(var(--input))',
        boxShadow: '0 1px 2px 0 rgb(0 0 0 / 0.05)',
    },
    '&.cm-editor.cm-focused': {
        outline: '2px solid hsl(var(--ring))',
        outlineOffset: '0px',
    },
    '&.cm-editor .cm-scroller': {
        fontFamily: 'inherit',
        lineHeight: '1.5',
    },
});
