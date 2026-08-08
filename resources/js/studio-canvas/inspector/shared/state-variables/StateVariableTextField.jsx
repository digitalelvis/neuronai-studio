import * as React from 'react';
import { FileText, Maximize } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import {
    parseTemplateRefs,
    toTemplate,
    useAvailableStateVariables,
} from '../stateVariables';
import StateVariablePicker from './StateVariablePicker';

const CHIP_ATTR = 'data-state-var-key';

function escapeHtml(text) {
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function groupClass(group) {
    if (group === 'start') {
        return 'border-sky-500/40 bg-sky-500/15 text-sky-100';
    }
    if (group === 'initial') {
        return 'border-emerald-500/40 bg-emerald-500/15 text-emerald-100';
    }
    if (group === 'system') {
        return 'border-amber-500/40 bg-amber-500/15 text-amber-100';
    }
    return 'border-primary/40 bg-primary/15 text-primary-foreground';
}

/**
 * @param {string} key
 * @param {Map<string, import('../stateVariables').StateVariable>} lookup
 */
function chipHtml(key, lookup) {
    const variable = lookup.get(key);
    const group = variable?.group || (key.startsWith('__') ? 'system' : 'node');
    const source = variable?.sourceLabel;
    const path = source && source !== key ? `${source} / ${key}` : key;
    const type = variable?.type || 'string';

    return (
        `<span ${CHIP_ATTR}="${escapeHtml(key)}" contenteditable="false" ` +
        `class="sv-chip inline-flex max-w-full items-center gap-1 rounded-md border px-1.5 py-0.5 text-[11px] font-medium leading-tight align-baseline mx-0.5 ${groupClass(group)}" ` +
        `title="${escapeHtml(path)}">` +
        '<span aria-hidden="true" class="opacity-80">{x}</span>' +
        `<span class="truncate">${escapeHtml(path)}</span>` +
        `<span class="shrink-0 text-[10px] font-normal opacity-60">${escapeHtml(type)}</span>` +
        `</span>`
    );
}

/**
 * @param {string} value
 * @param {Map<string, import('../stateVariables').StateVariable>} lookup
 */
function valueToHtml(value, lookup) {
    const source = typeof value === 'string' ? value : '';
    if (source === '') {
        return '';
    }

    const refs = parseTemplateRefs(source);
    if (refs.length === 0) {
        return escapeHtml(source);
    }

    let html = '';
    let cursor = 0;
    for (const ref of refs) {
        if (ref.start > cursor) {
            html += escapeHtml(source.slice(cursor, ref.start));
        }
        html += chipHtml(ref.key, lookup);
        cursor = ref.end;
    }
    if (cursor < source.length) {
        html += escapeHtml(source.slice(cursor));
    }
    return html;
}

/**
 * @param {HTMLElement|null} root
 */
function serializeEditor(root) {
    if (!root) {
        return '';
    }

    let out = '';

    const walk = (node) => {
        if (node.nodeType === Node.TEXT_NODE) {
            out += node.textContent ?? '';
            return;
        }

        if (node.nodeType !== Node.ELEMENT_NODE) {
            return;
        }

        const el = /** @type {HTMLElement} */ (node);
        const key = el.getAttribute?.(CHIP_ATTR);
        if (key) {
            out += toTemplate(key);
            return;
        }

        if (el.tagName === 'BR') {
            out += '\n';
            return;
        }

        el.childNodes.forEach(walk);
    };

    root.childNodes.forEach(walk);
    return out;
}

function placeCaretAtEnd(el) {
    const selection = window.getSelection();
    if (!selection || !el) {
        return;
    }
    const range = document.createRange();
    range.selectNodeContents(el);
    range.collapse(false);
    selection.removeAllRanges();
    selection.addRange(range);
}

/**
 * Contenteditable text field with inline state-variable chips.
 * Persists a plain string containing `{{key}}` templates.
 *
 * @param {{
 *   value?: string,
 *   onChange?: (event: { target: { value: string } }) => void,
 *   currentNodeId?: string|null,
 *   nodes?: unknown[],
 *   edges?: unknown[],
 *   variables?: import('../stateVariables').StateVariable[],
 *   placeholder?: string,
 *   disabled?: boolean,
 *   readOnly?: boolean,
 *   className?: string,
 *   rows?: number,
 *   compact?: boolean,
 *   label?: string,
 *   id?: string,
 *   name?: string,
 * }} props
 */
export default function StateVariableTextField({
    value = '',
    onChange,
    currentNodeId = null,
    nodes,
    edges,
    variables: variablesProp,
    placeholder = '',
    disabled = false,
    readOnly = false,
    className,
    rows = 3,
    compact = false,
    label = 'Edit text content',
    id,
    name,
}) {
    const catalog = useAvailableStateVariables(currentNodeId, { nodes, edges });
    const variables = variablesProp ?? catalog;
    const lookup = React.useMemo(() => {
        const map = new Map();
        for (const variable of variables) {
            map.set(variable.key, variable);
        }
        return map;
    }, [variables]);

    const editorRef = React.useRef(null);
    const expandedEditorRef = React.useRef(null);
    const lastEmitted = React.useRef(value ?? '');
    const [open, setOpen] = React.useState(false);
    const [pickerOpen, setPickerOpen] = React.useState(false);
    const [draft, setDraft] = React.useState(value ?? '');
    const [activeTarget, setActiveTarget] = React.useState(/** @type {'inline'|'expanded'} */ ('inline'));
    const locked = disabled || readOnly;
    const canExpand = !locked;

    const emitChange = React.useCallback(
        (next) => {
            lastEmitted.current = next;
            if (typeof onChange === 'function') {
                onChange({ target: { value: next, name, id } });
            }
        },
        [onChange, name, id],
    );

    const syncDom = React.useCallback(
        (el, nextValue) => {
            if (!el) {
                return;
            }
            const html = valueToHtml(nextValue ?? '', lookup);
            if (el.innerHTML !== html) {
                el.innerHTML = html;
            }
        },
        [lookup],
    );

    React.useEffect(() => {
        const next = value ?? '';
        if (next === lastEmitted.current) {
            syncDom(editorRef.current, next);
            return;
        }
        lastEmitted.current = next;
        syncDom(editorRef.current, next);
    }, [value, syncDom]);

    const handleEditorInput = (event) => {
        if (locked) {
            return;
        }

        const root = event.currentTarget;
        const next = serializeEditor(root);
        emitChange(next);
        if (open && root === expandedEditorRef.current) {
            setDraft(next);
        }
    };

    const handleKeyDown = (event) => {
        if (locked) {
            return;
        }

        if (event.key === '/' && !event.metaKey && !event.ctrlKey && !event.altKey) {
            event.preventDefault();
            setActiveTarget(event.currentTarget === expandedEditorRef.current ? 'expanded' : 'inline');
            setPickerOpen(true);
        }
    };

    const insertVariable = (variable) => {
        if (locked) {
            return;
        }

        const target =
            activeTarget === 'expanded' ? expandedEditorRef.current : editorRef.current;
        if (!target) {
            const next = `${lastEmitted.current || ''}${toTemplate(variable.key)}`;
            emitChange(next);
            if (open) {
                setDraft(next);
            }
            return;
        }

        target.focus();
        const selection = window.getSelection();
        if (!selection) {
            return;
        }

        let range;
        if (selection.rangeCount > 0 && target.contains(selection.anchorNode)) {
            range = selection.getRangeAt(0);
        } else {
            range = document.createRange();
            range.selectNodeContents(target);
            range.collapse(false);
        }

        range.deleteContents();
        const wrapper = document.createElement('span');
        wrapper.innerHTML = chipHtml(variable.key, lookup);
        const chip = wrapper.firstChild;
        if (chip) {
            range.insertNode(chip);
            const after = document.createTextNode('\u00a0');
            chip.after(after);
            range.setStartAfter(after);
            range.collapse(true);
            selection.removeAllRanges();
            selection.addRange(range);
        }

        const next = serializeEditor(target);
        emitChange(next);
        if (open) {
            setDraft(next);
            syncDom(editorRef.current, next);
        }
    };

    const openEditor = (event) => {
        event.preventDefault();
        event.stopPropagation();
        setDraft(value ?? '');
        setOpen(true);
        setActiveTarget('expanded');
    };

    const handleOpenChange = (next) => {
        if (!next) {
            setDraft(value ?? '');
        }
        setOpen(next);
    };

    const finishEditing = () => {
        const nextValue = draft ?? '';
        emitChange(nextValue);
        syncDom(editorRef.current, nextValue);
        setOpen(false);
    };

    React.useEffect(() => {
        if (!open) {
            return undefined;
        }
        const timer = window.setTimeout(() => {
            syncDom(expandedEditorRef.current, draft);
            placeCaretAtEnd(expandedEditorRef.current);
        }, 0);
        return () => window.clearTimeout(timer);
    }, [open]); // eslint-disable-line react-hooks/exhaustive-deps -- sync once on open

    const minHeight = compact ? '2rem' : `${Math.max(rows, 2) * 1.35}rem`;
    const toolbarPadding = locked ? undefined : 'pr-24';

    const insertTrigger = (
        <button
            type="button"
            disabled={locked}
            className="inline-flex h-6 items-center rounded-md px-1.5 font-mono text-[11px] text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50"
            title="Insert variable — click here or type /"
            aria-label="Insert variable"
        >
            {'{x} or /'}
        </button>
    );

    const editorSurface = (ref, extraClass) => (
        <div className="relative">
            {placeholder && !(ref === editorRef ? value : draft) && (
                <div className="pointer-events-none absolute left-3 top-2 text-sm text-muted-foreground">
                    {placeholder}
                </div>
            )}
            <div
                ref={ref}
                id={ref === editorRef ? id : undefined}
                role="textbox"
                aria-multiline="true"
                aria-readonly={locked || undefined}
                contentEditable={!locked}
                suppressContentEditableWarning
                className={cn(
                    'ab-state-var-editor relative z-[1] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring',
                    'whitespace-pre-wrap break-words',
                    locked && 'cursor-not-allowed opacity-60',
                    extraClass,
                )}
                style={{ minHeight }}
                onInput={handleEditorInput}
                onKeyDown={handleKeyDown}
                onBlur={() => {
                    // Normalize after blur so chips stay consistent.
                    syncDom(ref.current, lastEmitted.current);
                }}
            />
        </div>
    );

    return (
        <>
            <div className={cn('space-y-1.5', className)}>
                <div className="relative">
                    {editorSurface(editorRef, toolbarPadding)}
                    {!locked && (
                        <div className="absolute right-1.5 top-1.5 z-[2] flex items-center gap-0.5">
                            <StateVariablePicker
                                open={pickerOpen && activeTarget === 'inline'}
                                onOpenChange={(next) => {
                                    setActiveTarget('inline');
                                    setPickerOpen(next);
                                }}
                                variables={variables}
                                onSelect={insertVariable}
                                trigger={insertTrigger}
                            />
                            {canExpand && (
                                <button
                                    type="button"
                                    onClick={openEditor}
                                    className="inline-flex h-6 w-6 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                    title="Expand editor"
                                    aria-label="Expand editor"
                                >
                                    <Maximize className="h-3.5 w-3.5" />
                                </button>
                            )}
                        </div>
                    )}
                </div>
            </div>

            <Dialog open={open} onOpenChange={handleOpenChange}>
                <DialogContent className="flex h-[100dvh] w-screen max-w-none flex-col gap-4 rounded-none border-0 sm:rounded-none">
                    <DialogHeader className="shrink-0">
                        <DialogTitle className="flex items-center justify-between gap-2 text-base">
                            <span className="flex items-center gap-2">
                                <FileText className="h-4 w-4 text-muted-foreground" />
                                {label}
                            </span>
                            <StateVariablePicker
                                open={pickerOpen && activeTarget === 'expanded'}
                                onOpenChange={(next) => {
                                    setActiveTarget('expanded');
                                    setPickerOpen(next);
                                }}
                                variables={variables}
                                onSelect={insertVariable}
                                trigger={insertTrigger}
                                align="end"
                            />
                        </DialogTitle>
                    </DialogHeader>

                    <div className="relative min-h-0 flex-1">
                        {editorSurface(
                            expandedEditorRef,
                            'h-full min-h-[50vh] overflow-y-auto font-mono text-sm',
                        )}
                    </div>

                    <DialogFooter className="shrink-0">
                        <Button type="button" onClick={finishEditing}>
                            Finish Editing
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
