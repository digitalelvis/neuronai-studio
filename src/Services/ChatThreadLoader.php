<?php

namespace DigitalElvis\NeuronAIStudio\Services;

use DigitalElvis\NeuronAIStudio\Models\StudioChatMessage;
use DigitalElvis\NeuronAIStudio\Support\ChatThreadKey;

class ChatThreadLoader
{
    /**
     * @return array{thread_id: string, messages: array<int, array{id: string, role: string, content: string}>}
     */
    public function loadForAgent(int $agentId, string $threadId): array
    {
        if (str_contains($threadId, ':')) {
            $threadId = ChatThreadKey::publicId($threadId);
        }

        return $this->loadMessages($threadId, [
            $threadId,
            ChatThreadKey::forAgent($agentId, $threadId),
            ChatThreadKey::forWorkflow($agentId, $threadId),
        ]);
    }

    /**
     * @return array{thread_id: string, messages: array<int, array{id: string, role: string, content: string}>}
     */
    public function loadForWorkflow(int $workflowId, string $threadId): array
    {
        if (str_contains($threadId, ':')) {
            $threadId = ChatThreadKey::publicId($threadId);
        }

        return $this->loadMessages($threadId, [
            $threadId,
            ChatThreadKey::forWorkflow($workflowId, $threadId),
        ]);
    }

    /**
     * @param  list<string>  $keys
     * @return array{thread_id: string, messages: array<int, array{id: string, role: string, content: string}>}
     */
    protected function loadMessages(string $publicThreadId, array $keys): array
    {
        $records = StudioChatMessage::query()
            ->whereIn('thread_id', array_unique($keys))
            ->orderBy('id')
            ->get(['id', 'role', 'content']);

        $messages = [];

        foreach ($records as $record) {
            $role = (string) $record->role;

            if (! in_array($role, ['user', 'assistant'], true)) {
                continue;
            }

            $messages[] = [
                'id' => 'msg_'.$record->id,
                'role' => $role,
                'content' => $this->textFromContent($record->content),
            ];
        }

        return [
            'thread_id' => $publicThreadId,
            'messages' => $messages,
        ];
    }

    protected function textFromContent(mixed $content): string
    {
        if (is_string($content)) {
            return $content;
        }

        if (! is_array($content)) {
            return '';
        }

        $parts = [];

        foreach ($content as $block) {
            if (is_string($block)) {
                $parts[] = $block;

                continue;
            }

            if (! is_array($block)) {
                continue;
            }

            if (isset($block['content']) && is_string($block['content'])) {
                $parts[] = $block['content'];

                continue;
            }

            if (isset($block['text']) && is_string($block['text'])) {
                $parts[] = $block['text'];
            }
        }

        return implode('', $parts);
    }
}
