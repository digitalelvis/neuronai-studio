import { json, jsonParseLinter } from '@codemirror/lang-json';
import { php } from '@codemirror/lang-php';
import { bracketMatching, indentOnInput } from '@codemirror/language';
import { linter, lintGutter } from '@codemirror/lint';
import { indentWithTab } from '@codemirror/commands';
import {
    EditorView,
    keymap,
    lineNumbers,
    highlightActiveLineGutter,
    highlightActiveLine,
} from '@codemirror/view';
import { studioTheme, studioEditorStyles, studioSyntaxHighlight } from './studioTheme';

const languageExtensions = {
    json: () => [json()],
    php: () => [php()],
    plaintext: () => [],
};

export function buildCodeExtensions({ language = 'json', lint = true, readOnly = false, lineWrapping = true } = {}) {
    const extensions = [
        studioTheme,
        studioEditorStyles,
        studioSyntaxHighlight,
        lineNumbers(),
        highlightActiveLineGutter(),
        highlightActiveLine(),
        bracketMatching(),
        indentOnInput(),
        keymap.of([indentWithTab]),
        EditorView.editable.of(!readOnly),
        ...(languageExtensions[language]?.() ?? []),
    ];

    if (lineWrapping) {
        extensions.push(EditorView.lineWrapping);
    }

    if (lint && language === 'json' && !readOnly) {
        extensions.push(lintGutter(), linter(jsonParseLinter()));
    }

    return extensions;
}
