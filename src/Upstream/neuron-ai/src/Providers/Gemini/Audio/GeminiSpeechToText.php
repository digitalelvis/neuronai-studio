<?php

declare(strict_types=1);

namespace NeuronAI\Providers\Gemini\Audio;

use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Exceptions\ProviderException;
use NeuronAI\HttpClient\GuzzleHttpClient;
use NeuronAI\HttpClient\HttpClientInterface;
use NeuronAI\HttpClient\HttpRequest;
use NeuronAI\Providers\Gemini\GeminiMediaProvider;

use function base64_encode;
use function end;
use function file_get_contents;
use function is_file;

/**
 * Gemini transcription models via generateContent with inline audio.
 */
class GeminiSpeechToText extends GeminiMediaProvider
{
    /**
     * @param  array<string, mixed>  $parameters
     */
    public function __construct(
        protected string $key,
        protected string $model,
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
        $audio = $message->getAudio();

        if ($audio === null || $audio->content === '') {
            throw new ProviderException('Gemini transcription requires an audio file.');
        }

        $payload = is_file($audio->content)
            ? base64_encode((string) file_get_contents($audio->content))
            : $audio->content;

        $prompt = $this->promptFrom($message);
        if ($prompt === '') {
            $prompt = 'Transcribe this audio.';
        }

        $body = [
            'contents' => [[
                'role' => 'user',
                'parts' => [
                    ['text' => $prompt],
                    ['inlineData' => [
                        'mimeType' => $audio->mediaType ?? 'audio/mpeg',
                        'data' => $payload,
                    ]],
                ],
            ]],
            ...($this->parameters === [] ? [] : ['generationConfig' => $this->parameters]),
        ];

        $result = $this->httpClient->request(
            HttpRequest::post(uri: 'models/'.$this->model.':generateContent', body: $body)
        )->json();

        $this->assertNoApiError($result);

        $text = '';
        foreach ($result['candidates'][0]['content']['parts'] ?? [] as $part) {
            if (isset($part['text'])) {
                $text .= (string) $part['text'];
            }
        }

        $text = trim($text);
        if ($text === '') {
            throw new ProviderException('Gemini transcription returned no text.');
        }

        return new AssistantMessage($text);
    }
}
