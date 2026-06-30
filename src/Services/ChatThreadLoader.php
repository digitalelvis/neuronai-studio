<?php

namespace DigitalElvis\NeuronAIStudio\Services;

use DigitalElvis\NeuronAIStudio\Models\StudioChatMessage;
use DigitalElvis\NeuronAIStudio\Support\ChatThreadKey;

class ChatThreadLoader
{
    /**
     * @return array{thread_id: string, messages: array<int, array{role: string, content: string}>}
     */
    public function loadForAgent(int $agentId, string $threadId): array
    {
        $scopedKey = ChatThreadKey::forAgent($agentId, $threadId);

        $records = StudioChatMessage::query()
            ->where('thread_id', $scopedKey)
            ->orderBy('id')
            ->get(['role', 'content']);

        $messages = [];

        foreach ($records as $record) {
            $role = (string) $record->role;

            if (! in_array($role, ['user', 'assistant'], true)) {
                continue;
            }

            $messages[] = [
                'role' => $role,
                'content' => $this->textFromContent($record->content),
            ];
        }

        return [
            'thread_id' => $threadId,
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
