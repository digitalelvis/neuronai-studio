export function createId(prefix = 'msg') {
    return `${prefix}_${Date.now()}_${Math.random().toString(36).slice(2, 9)}`;
}
