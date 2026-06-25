<?php

namespace ElvisLopesDigital\NeuronAIStudio\Support;

use Illuminate\Support\Str;

class ChatThreadKey
{
    public static function forAgent(int $agentId, ?string $threadId = null): string
    {
        $threadId = self::normalizePublicId($threadId);

        return "agent:{$agentId}:{$threadId}";
    }

    public static function forWorkflow(int $workflowId, ?string $threadId = null): string
    {
        $threadId = self::normalizePublicId($threadId);

        return "workflow:{$workflowId}:{$threadId}";
    }

    protected static function normalizePublicId(?string $threadId): string
    {
        return $threadId !== null && $threadId !== ''
            ? $threadId
            : (string) Str::uuid();
    }

    public static function publicId(string $scopedKey): string
    {
        $parts = explode(':', $scopedKey, 3);

        return $parts[2] ?? $scopedKey;
    }
}
