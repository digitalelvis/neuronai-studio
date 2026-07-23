import {
    Bot,
    Circle,
    Clock,
    Code2,
    Database,
    GitBranch,
    GitFork,
    GitMerge,
    MessageSquare,
    Plug,
    Repeat,
    Search,
    Square,
    StickyNote,
    Wrench,
    Play,
} from 'lucide-react';

const ICON_MAP = {
    play: Play,
    stop: Square,
    bot: Bot,
    'message-square': MessageSquare,
    'git-branch': GitBranch,
    'git-fork': GitFork,
    'git-merge': GitMerge,
    database: Database,
    code: Code2,
    wrench: Wrench,
    search: Search,
    clock: Clock,
    repeat: Repeat,
    plug: Plug,
    sticky: StickyNote,
    circle: Circle,
};

export function NodeTypeIcon({ name, className = 'h-3.5 w-3.5' }) {
    const Icon = ICON_MAP[name] || Circle;
    return <Icon className={className} />;
}

export function iconForNodeType(icon) {
    return ICON_MAP[icon] || Circle;
}
