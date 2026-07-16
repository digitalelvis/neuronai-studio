export function formatTokens(value) {
    const tokens = Number(value ?? 0);

    if (tokens >= 1000) {
        return `${(tokens / 1000).toFixed(1).replace(/\.0$/, '')}k tok`;
    }

    return `${tokens} tok`;
}

export function formatCost(value, currency = 'USD') {
    return `${currency || 'USD'} ${Number(value ?? 0).toFixed(2)}`;
}

