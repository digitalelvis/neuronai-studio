<?php

namespace ElvisLopesDigital\NeuronAIStudio\Support;

use Illuminate\Support\Str;

class ChatThreadKey
{
    public static function forAgent(int $agentId, ?string $threadId = null): string
    {
        $threadId = $threadId !== null && $threadId !== ''
            ? $threadId
            : (string) Str::uuid();

        return "agent:{$agentId}:{$threadId}";
    }

    public static function publicId(string $scopedKey): string
    {
        $parts = explode(':', $scopedKey, 3);

        return $parts[2] ?? $scopedKey;
    }
}
