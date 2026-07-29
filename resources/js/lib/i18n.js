/**
 * Studio JS i18n helper. Catalog is injected by Blade as window.__STUDIO_I18N__.
 *
 * @param {string} key
 * @param {Record<string, string|number>} [replace]
 * @returns {string}
 */
export function t(key, replace = {}) {
    const catalog = (typeof window !== 'undefined' && window.__STUDIO_I18N__?.messages) || {};
    let value = catalog[key] ?? key;

    if (replace && typeof value === 'string') {
        for (const [name, replacement] of Object.entries(replace)) {
            value = value.replace(new RegExp(`:${name}`, 'g'), String(replacement));
        }
    }

    return value;
}

export function studioLocale() {
    return (typeof window !== 'undefined' && window.__STUDIO_I18N__?.locale) || 'en';
}
