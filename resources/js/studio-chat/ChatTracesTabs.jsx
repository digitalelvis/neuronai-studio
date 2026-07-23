import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { cn } from '@/lib/utils';

export default function ChatTracesTabs({ value = 'chat', onValueChange, className }) {
    return (
        <Tabs value={value} onValueChange={onValueChange} className={cn('w-auto', className)}>
            <TabsList className="h-8">
                <TabsTrigger value="chat" className="h-7 px-4 text-xs">
                    Chat
                </TabsTrigger>
                <TabsTrigger value="traces" className="h-7 px-4 text-xs">
                    Traces
                </TabsTrigger>
            </TabsList>
        </Tabs>
    );
}
