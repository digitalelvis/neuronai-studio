<?php

declare(strict_types=1);

namespace NeuronAI\Providers\Gemini;

use Generator;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Exceptions\ProviderException;
use NeuronAI\HttpClient\HasHttpClient;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\MessageMapperInterface;
use NeuronAI\Providers\ToolMapperInterface;

/**
 * Shared surface for Gemini media drivers contributed upstream.
 * Chat models stay on {@see Gemini}; these classes call other generation contracts.
 */
abstract class GeminiMediaProvider implements AIProviderInterface
{
    use HasHttpClient;

    protected ?string $system = null;

    protected string $baseUri = 'https://generativelanguage.googleapis.com/v1beta';

    public function systemPrompt(?string $prompt): AIProviderInterface
    {
        $this->system = $prompt;

        return $this;
    }

    public function stream(Message ...$messages): Generator
    {
        throw new ProviderException('Streaming is not supported by this Gemini media provider.');
    }

    public function structured(array|Message $messages, string $class, array $response_schema): Message
    {
        throw new ProviderException('Structured output is not supported by this Gemini media provider.');
    }

    public function messageMapper(): MessageMapperInterface
    {
        throw new ProviderException('Message mapping is not supported by this Gemini media provider.');
    }

    public function toolPayloadMapper(): ToolMapperInterface
    {
        throw new ProviderException('Tools are not supported by this Gemini media provider.');
    }

    public function setTools(array $tools): AIProviderInterface
    {
        return $this;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function assertNoApiError(array $result): void
    {
        if (isset($result['error'])) {
            $message = is_array($result['error'])
                ? (string) ($result['error']['message'] ?? json_encode($result['error']))
                : (string) $result['error'];

            throw new ProviderException('Gemini API Error: '.$message);
        }
    }

    protected function promptFrom(Message $message): string
    {
        $prompt = trim((string) ($message->getContent() ?? ''));

        if ($this->system !== null && $this->system !== '') {
            $prompt = trim($this->system."\n\n".$prompt);
        }

        return $prompt;
    }
}
