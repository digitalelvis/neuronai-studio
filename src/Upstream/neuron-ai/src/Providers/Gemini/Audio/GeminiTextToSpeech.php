<?php

declare(strict_types=1);

namespace NeuronAI\Providers\Gemini\Audio;

use NeuronAI\Chat\Enums\SourceType;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\ContentBlocks\AudioContent;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Exceptions\ProviderException;
use NeuronAI\HttpClient\GuzzleHttpClient;
use NeuronAI\HttpClient\HttpClientInterface;
use NeuronAI\HttpClient\HttpRequest;
use NeuronAI\Providers\Gemini\GeminiMediaProvider;

use function end;

/**
 * Gemini TTS models via generateContent with AUDIO modality.
 *
 * https://ai.google.dev/gemini-api/docs/speech-generation
 */
class GeminiTextToSpeech extends GeminiMediaProvider
{
    /**
     * @param  array<string, mixed>  $parameters
     */
    public function __construct(
        protected string $key,
        protected string $model,
        protected string $voice = 'Kore',
        protected array $parameters = [],
        ?HttpClientInterface $httpClient = null,
    ) {
        $this->httpClient = ($httpClient ?? new GuzzleHttpClient())
            ->withBaseUri($this->baseUri)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'x-goog-api-key' => $this->key,
            ]);
    }

    public function chat(Message ...$messages): Message
    {
        $message = end($messages);
        $prompt = $this->promptFrom($message);

        if ($prompt === '') {
            throw new ProviderException('Gemini speech generation requires text input.');
        }

        $body = [
            'contents' => [[
                'role' => 'user',
                'parts' => [['text' => $prompt]],
            ]],
            'generationConfig' => [
                'responseModalities' => ['AUDIO'],
                'speechConfig' => [
                    'voiceConfig' => [
                        'prebuiltVoiceConfig' => [
                            'voiceName' => $this->voice,
                        ],
                    ],
                ],
                ...$this->parameters,
            ],
        ];

        $result = $this->httpClient->request(
            HttpRequest::post(uri: 'models/'.$this->model.':generateContent', body: $body)
        )->json();

        $this->assertNoApiError($result);

        $parts = $result['candidates'][0]['content']['parts'] ?? [];
        foreach ($parts as $part) {
            $inline = $part['inlineData'] ?? $part['inline_data'] ?? null;
            if (! is_array($inline) || ($inline['data'] ?? '') === '') {
                continue;
            }

            $mime = (string) ($inline['mimeType'] ?? $inline['mime_type'] ?? 'audio/wav');

            return new AssistantMessage(
                new AudioContent((string) $inline['data'], SourceType::BASE64, $mime)
            );
        }

        throw new ProviderException('Gemini speech generation returned no audio data.');
    }
}
